<?php
include("../includes/auth.php");
include("../config/db.php");

$user_id = $_SESSION["user_id"] ?? 0;
$class_id = $_SESSION["class_id"] ?? 0;

if (!$user_id || !$class_id) {
    die("Unauthorized.");
}

/* =========================
   BASIC DATA
========================= */
$material_id = isset($_POST["material_id"]) && $_POST["material_id"] !== ""
    ? (int)$_POST["material_id"]
    : null;

$subject_id = isset($_POST["subject_id"]) && $_POST["subject_id"] !== ""
    ? (int)$_POST["subject_id"]
    : null;

$description = trim($_POST["description"] ?? "");
$notes = trim($_POST["notes"] ?? "");
$deadline = trim($_POST["deadline"] ?? "");

/*
   IMPORTANT:
   If deadline is empty, set it to tomorrow.
   This prevents empty deadline from becoming overdue.
*/
if ($deadline === "" || $deadline === "0000-00-00") {
    $deadline = date("Y-m-d", strtotime("+1 day"));
}

/* =========================
   COLLECT TASKS
========================= */
$tasksToAdd = [];

/* AI-generated tasks */
if (isset($_POST["selected_tasks"]) && is_array($_POST["selected_tasks"])) {
    foreach ($_POST["selected_tasks"] as $task) {
        $task = trim($task);

        if ($task !== "") {
            $tasksToAdd[] = $task;
        }
    }
}

/* Manual task */
$manualTask = trim($_POST["title"] ?? "");

if ($manualTask !== "") {
    $tasksToAdd[] = $manualTask;
}

/* =========================
   VALIDATION
========================= */
if (empty($tasksToAdd)) {
    header("Location: ../tasks.php?error=no_task");
    exit();
}

/* =========================
   MATERIAL INFO
========================= */
if ($material_id) {
    $stmt = $conn->prepare("
        SELECT id, title, subject_id
        FROM materials
        WHERE id = ?
        AND class_id = ?
        LIMIT 1
    ");

    $stmt->bind_param("ii", $material_id, $class_id);
    $stmt->execute();

    $material = $stmt->get_result()->fetch_assoc();

    if ($material) {
        $subject_id = (int)$material["subject_id"];

        if ($description === "") {
            $description = "AI-generated task from uploaded study material";
        }

        if ($notes === "") {
            $notes = "Generated from study material: " . $material["title"];
        }
    }
}

/* =========================
   INSERT TASKS
========================= */
$stmt = $conn->prepare("
    INSERT INTO tasks
    (user_id, class_id, subject_id, material_id, title, description, notes, deadline, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
");

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$created = 0;

foreach ($tasksToAdd as $taskTitle) {
    $stmt->bind_param(
        "iiiissss",
        $user_id,
        $class_id,
        $subject_id,
        $material_id,
        $taskTitle,
        $description,
        $notes,
        $deadline
    );

    if ($stmt->execute()) {
        $created++;
    }
}

/* =========================
   REDIRECT
========================= */
if ($material_id) {
    header("Location: ../dashboard.php?tasks_added=" . $created);
} else {
    header("Location: ../tasks.php?tasks_added=" . $created);
}

exit();
?>
