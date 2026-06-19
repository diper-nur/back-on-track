<?php
include("includes/auth.php");
include("config/db.php");
include("includes/lang.php");
include("includes/header.php");
include("includes/navbar.php");

$user_id = $_SESSION["user_id"] ?? 0;

if (!$user_id) {
    echo "<p>Unauthorized.</p>";
    include("includes/footer.php");
    exit();
}

/* =========================
   GET USER CLASSES
========================= */
$stmt = $conn->prepare("
    SELECT c.id, c.name
    FROM classes c
    JOIN class_members cm ON c.id = cm.class_id
    WHERE cm.user_id = ?
    ORDER BY c.name ASC
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$classes = $stmt->get_result();

/* =========================
   AUTO RESTORE CLASS
========================= */
$class_id = $_SESSION["class_id"] ?? null;

if (!$class_id) {
    $stmt = $conn->prepare("
        SELECT class_id
        FROM class_members
        WHERE user_id = ?
        LIMIT 1
    ");

    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $row = $stmt->get_result()->fetch_assoc();

    if ($row) {
        $class_id = (int)$row["class_id"];
        $_SESSION["class_id"] = $class_id;
    }
}

/* =========================
   CHECK CLASS
========================= */
if (!$class_id) {
    echo "<p>" . t("join_class_first") . "</p>";
    include("includes/footer.php");
    exit();
}

/* =========================
   SWITCH CLASS
========================= */
if (isset($_POST["switch_class"])) {
    $_SESSION["class_id"] = (int)$_POST["class_id"];
    header("Location: dashboard.php");
    exit();
}

/* =========================
   CLASS INFO
========================= */
$stmt = $conn->prepare("
    SELECT name
    FROM classes
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $class_id);
$stmt->execute();

$class = $stmt->get_result()->fetch_assoc();
$class_name = $class["name"] ?? "Unknown";

/* =========================
   TASK STATS
   IMPORTANT:
   Database uses status='completed', not status='done'
========================= */
$stmt = $conn->prepare("
    SELECT COUNT(*) AS c
    FROM tasks
    WHERE user_id = ?
    AND class_id = ?
");

$stmt->bind_param("ii", $user_id, $class_id);
$stmt->execute();

$total = (int)($stmt->get_result()->fetch_assoc()["c"] ?? 0);

$stmt = $conn->prepare("
    SELECT COUNT(*) AS c
    FROM tasks
    WHERE user_id = ?
    AND class_id = ?
    AND status = 'completed'
");

$stmt->bind_param("ii", $user_id, $class_id);
$stmt->execute();

$done = (int)($stmt->get_result()->fetch_assoc()["c"] ?? 0);

$pending = max(0, $total - $done);
$progress = ($total > 0) ? round(($done / $total) * 100) : 0;

/* =========================
   DEADLINE NOTIFICATIONS
   Uses deadline first, then due_date
========================= */
$upcoming_stmt = $conn->prepare("
    SELECT
        title,
        COALESCE(
            NULLIF(deadline, '0000-00-00'),
            NULLIF(due_date, '0000-00-00')
        ) AS task_deadline
    FROM tasks
    WHERE user_id = ?
    AND class_id = ?
    AND status = 'pending'
    AND COALESCE(
        NULLIF(deadline, '0000-00-00'),
        NULLIF(due_date, '0000-00-00')
    ) IS NOT NULL
    AND COALESCE(
        NULLIF(deadline, '0000-00-00'),
        NULLIF(due_date, '0000-00-00')
    ) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 2 DAY)
    ORDER BY task_deadline ASC
");

$upcoming_stmt->bind_param("ii", $user_id, $class_id);
$upcoming_stmt->execute();

$notifications = $upcoming_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$message = "";

if (isset($_GET["tasks_added"])) {
    $message = "✅ Tasks added: " . (int)$_GET["tasks_added"];
}
?>

<h2><?= t("dashboard") ?></h2>

<?php if ($message): ?>
    <p style="color:green;"><?= htmlspecialchars($message) ?></p>
<?php endif; ?>

<?php if (!empty($notifications)): ?>
    <div id="deadlineAlert" style="
        position:fixed;
        top:20px;
        right:20px;
        background:#fff;
        border-left:6px solid #ff9800;
        padding:15px;
        width:300px;
        box-shadow:0 5px 15px rgba(0,0,0,0.2);
        border-radius:10px;
        z-index:9999;
    ">
        <strong><?= t("upcoming_deadlines") ?></strong><br><br>

        <?php foreach ($notifications as $n): ?>
            <div style="margin-bottom:8px;">
                📌 <?= htmlspecialchars($n["title"]) ?><br>
                <small><?= t("due") ?>: <?= htmlspecialchars($n["task_deadline"]) ?></small>
            </div>
        <?php endforeach; ?>

        <button onclick="closeAlert()" style="
            margin-top:10px;
            padding:5px 10px;
            border:none;
            background:#f44336;
            color:white;
            border-radius:5px;
            cursor:pointer;
        ">
            <?= t("dismiss") ?>
        </button>
    </div>

    <script>
        function closeAlert() {
            document.getElementById("deadlineAlert").style.display = "none";
        }
    </script>
<?php endif; ?>

<div style="display:flex; justify-content:space-between; margin-bottom:15px;">
    <form method="POST">
        <select name="class_id" onchange="this.form.submit()">
            <?php while ($c = $classes->fetch_assoc()): ?>
                <option
                    value="<?= (int)$c["id"] ?>"
                    <?= ((int)$c["id"] === (int)$class_id) ? "selected" : "" ?>
                >
                    <?= htmlspecialchars($c["name"]) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <input type="hidden" name="switch_class" value="1">
    </form>
</div>

<h3><?= t("class") ?>: <?= htmlspecialchars($class_name) ?></h3>

<div class="dashboard-grid">
    <div class="stats">
        <div class="box"><?= t("total_tasks") ?>: <?= $total ?></div>
        <div class="box"><?= t("completed") ?>: <?= $done ?></div>
        <div class="box"><?= t("pending") ?>: <?= $pending ?></div>
        <div class="box"><?= t("progress") ?>: <?= $progress ?>%</div>
    </div>

    <div class="chart-container">
        <canvas id="progressChart"></canvas>
    </div>
</div>

<h3><?= t("Your_Tasks") ?></h3>

<?php
$stmt = $conn->prepare("
    SELECT
        *,
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

$stmt->bind_param("ii", $user_id, $class_id);
$stmt->execute();

$tasks = $stmt->get_result();

if ($tasks && $tasks->num_rows > 0) {
    echo "<table class='task-table'>";
    echo "<tr>
            <th>" . t("task") . "</th>
            <th>" . t("description") . "</th>
            <th>" . t("notes") . "</th>
            <th>" . t("deadline") . "</th>
            <th>" . t("status") . "</th>
          </tr>";

    while ($t = $tasks->fetch_assoc()) {
        $status = $t["status"] ?? "pending";
        $deadline = $t["task_deadline"] ?? "";

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
        echo "<td>" . htmlspecialchars($t["title"] ?? "") . "</td>";
        echo "<td>" . htmlspecialchars($t["description"] ?? "") . "</td>";
        echo "<td>" . htmlspecialchars($t["notes"] ?? "") . "</td>";
        echo "<td>" . htmlspecialchars($hasDeadline ? $deadline : "-") . "</td>";
        echo "<td>" . $badge . "</td>";
        echo "</tr>";
    }

    echo "</table>";
} else {
    echo "<p>" . t("no_tasks") . "</p>";
}
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
new Chart(document.getElementById('progressChart'), {
    type: 'doughnut',
    data: {
        labels: ['<?= t("completed") ?>', '<?= t("pending") ?>'],
        datasets: [{
            data: [<?= $done ?>, <?= $pending ?>]
        }]
    }
});
</script>

<?php include("includes/footer.php"); ?>