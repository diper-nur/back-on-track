<?php
include("includes/auth.php");
include("config/db.php");
include("includes/lang.php");
include("includes/header.php");
include("includes/navbar.php");

$user_id = $_SESSION["user_id"] ?? 0;
$class_id = $_SESSION["class_id"] ?? 0;

if (!$user_id) {
    echo "<p>Unauthorized.</p>";
    include("includes/footer.php");
    exit();
}

if (!$class_id) {
    echo "<p>" . t("join_class_first") . "</p>";
    include("includes/footer.php");
    exit();
}

/* =========================
   GET CLASS NAME
========================= */
$stmt = $conn->prepare("
    SELECT name
    FROM classes
    WHERE id = ?
    LIMIT 1
");

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("i", $class_id);
$stmt->execute();

$class = $stmt->get_result()->fetch_assoc();
$className = $class["name"] ?? "";

/* =========================
   PAGE MESSAGE
========================= */
$message = "";

if (isset($_GET["tasks_added"])) {
    $message = "Tasks added: " . (int)$_GET["tasks_added"];
}

if (isset($_GET["error"]) && $_GET["error"] === "no_task") {
    $message = "Please write a task title.";
}
?>

<h2><?= t("my_tasks") ?></h2>

<h3><?= t("class") ?>: <?= htmlspecialchars($className) ?></h3>

<?php if ($message): ?>
    <p style="color:green;">
        <?= htmlspecialchars($message) ?>
    </p>
<?php endif; ?>

<!-- ADD TASK -->
<form method="POST" action="actions/create-task.php" class="task-form">

    <input
        type="text"
        name="title"
        placeholder="<?= t("task_title") ?>"
        required
    >

    <input
        type="text"
        name="description"
        placeholder="<?= t("task_description") ?>"
    >

    <input
        type="text"
        name="notes"
        placeholder="<?= t("task_notes") ?>"
    >

    <input
        type="date"
        name="deadline"
        required
    >

    <button type="submit">
        <?= t("add_task") ?>
    </button>

</form>

<hr>

<h3><?= t("Your_Tasks") ?></h3>

<?php
/* =========================
   GET TASKS
   Table uses:
   title, description, notes, status, due_date, deadline
========================= */
$stmt = $conn->prepare("
    SELECT
        id,
        user_id,
        class_id,
        subject_id,
        material_id,
        title,
        description,
        notes,
        status,
        due_date,
        deadline,
        created_at,
        COALESCE(
            NULLIF(deadline, '0000-00-00'),
            NULLIF(due_date, '0000-00-00')
        ) AS task_deadline
    FROM tasks
    WHERE user_id = ?
    AND class_id = ?
    ORDER BY
        CASE
            WHEN status = 'completed' THEN 5
            WHEN COALESCE(NULLIF(deadline, '0000-00-00'), NULLIF(due_date, '0000-00-00')) IS NULL THEN 4
            WHEN COALESCE(NULLIF(deadline, '0000-00-00'), NULLIF(due_date, '0000-00-00')) < CURDATE() THEN 1
            WHEN COALESCE(NULLIF(deadline, '0000-00-00'), NULLIF(due_date, '0000-00-00')) = CURDATE() THEN 2
            WHEN COALESCE(NULLIF(deadline, '0000-00-00'), NULLIF(due_date, '0000-00-00')) <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 3
            ELSE 4
        END,
        task_deadline ASC,
        created_at DESC
");

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("ii", $user_id, $class_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {

    echo "<table class='task-table'>";

    echo "<tr>
            <th>" . t("task") . "</th>
            <th>" . t("description") . "</th>
            <th>" . t("notes") . "</th>
            <th>" . t("deadline") . "</th>
            <th>" . t("status") . "</th>
            <th>" . t("actions") . "</th>
          </tr>";

    while ($row = $result->fetch_assoc()) {

        $status = $row["status"] ?? "pending";
        $deadline = $row["task_deadline"] ?? "";

        $hasDeadline = (
            $deadline !== "" &&
            $deadline !== null &&
            $deadline !== "0000-00-00"
        );

        $isOverdue = (
            $status !== "completed" &&
            $hasDeadline &&
            $deadline < date("Y-m-d")
        );

        if ($status === "completed") {
            $badge = "<span class='badge done'>" . t("completed") . "</span>";
        } elseif ($isOverdue) {
            $badge = "<span class='badge overdue'>" . t("overdue") . "</span>";
        } else {
            $badge = "<span class='badge pending'>" . t("pending") . "</span>";
        }

        echo "<tr>";

        echo "<td>" . htmlspecialchars($row["title"] ?? "") . "</td>";

        echo "<td>" . nl2br(htmlspecialchars($row["description"] ?? "")) . "</td>";

        echo "<td>" . nl2br(htmlspecialchars($row["notes"] ?? "")) . "</td>";

        echo "<td>" . htmlspecialchars($hasDeadline ? $deadline : "-") . "</td>";

        echo "<td>" . $badge . "</td>";

        echo "<td>";

        if ($status === "pending") {
            echo "<a href='actions/complete-task.php?id=" . (int)$row["id"] . "' title='" . t("mark_done") . "'>✔</a> ";
        }

        echo "<a href='actions/delete-task.php?id=" . (int)$row["id"] . "' title='" . t("delete") . "'>❌</a>";

        echo "</td>";

        echo "</tr>";
    }

    echo "</table>";

} else {
    echo "<p>" . t("no_tasks") . "</p>";
}
?>

<?php include("includes/footer.php"); ?>