<?php
include("../includes/auth.php");
include("../config/db.php");

$user_id = $_SESSION["user_id"] ?? 0;
$class_id = $_SESSION["class_id"] ?? 0;

if (!$user_id || !$class_id) {
    die("Unauthorized.");
}

/* =========================
   SAFE REDIRECT BACK
========================= */
function redirectBack($params = []) {
    $fallback = "../tasks.php";
    $redirect = $_SERVER["HTTP_REFERER"] ?? $fallback;

    $currentHost = $_SERVER["HTTP_HOST"] ?? "";
    $redirectHost = parse_url($redirect, PHP_URL_HOST);

    if ($redirectHost && $currentHost && strtolower($redirectHost) !== strtolower($currentHost)) {
        $redirect = $fallback;
    }

    if (!empty($params)) {
        $separator = (strpos($redirect, "?") === false) ? "?" : "&";
        $redirect .= $separator . http_build_query($params);
    }

    header("Location: " . $redirect);
    exit();
}

/* =========================
   READ FORM DATA
========================= */
$title = trim($_POST["title"] ?? "");
$description = trim($_POST["description"] ?? "");
$notes = trim($_POST["notes"] ?? "");

$material_id = isset($_POST["material_id"]) && $_POST["material_id"] !== ""
    ? (int)$_POST["material_id"]
    : null;

$subject_id = isset($_POST["subject_id"]) && $_POST["subject_id"] !== ""
    ? (int)$_POST["subject_id"]
    : null;

/* =========================
   DEADLINE
========================= */
$deadline = trim($_POST["deadline"] ?? $_POST["due_date"] ?? "");

if ($deadline === "" || $deadline === "0000-00-00") {
    $deadline = date("Y-m-d", strtotime("+1 day"));
}

$dateCheck = DateTime::createFromFormat("Y-m-d", $deadline);

if (!$dateCheck || $dateCheck->format("Y-m-d") !== $deadline) {
    $deadline = date("Y-m-d", strtotime("+1 day"));
}

/* =========================
   COLLECT TASKS
========================= */
$tasksToAdd = [];

/* Manual task from tasks.php */
if ($title !== "") {
    $tasksToAdd[] = $title;
}

/* AI-generated tasks from material-detail.php */
if (isset($_POST["selected_tasks"]) && is_array($_POST["selected_tasks"])) {
    foreach ($_POST["selected_tasks"] as $task) {
        $task = trim($task);

        if ($task !== "") {
            $tasksToAdd[] = $task;
        }
    }
}

if (empty($tasksToAdd)) {
    redirectBack(["error" => "no_task"]);
}

/* =========================
   MATERIAL INFO FOR AI TASKS
========================= */
if ($material_id !== null) {
    $stmt = $conn->prepare("
        SELECT title, subject_id
        FROM materials
        WHERE id = ?
        AND class_id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        die("Prepare material failed: " . $conn->error);
    }

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
   Required DB columns:
   user_id, class_id, subject_id, material_id,
   title, description, notes, status, due_date, deadline
========================= */
$stmt = $conn->prepare("
    INSERT INTO tasks
    (user_id, class_id, subject_id, material_id, title, description, notes, status, due_date, deadline)
    VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?)
");

if (!$stmt) {
    die("Prepare insert failed: " . $conn->error);
}

$created = 0;

foreach ($tasksToAdd as $taskTitle) {
    $stmt->bind_param(
        "iiiisssss",
        $user_id,
        $class_id,
        $subject_id,
        $material_id,
        $taskTitle,
        $description,
        $notes,
        $deadline,
        $deadline
    );

    if ($stmt->execute()) {
        $created++;
    }
}

/* =========================
   REDIRECT BACK TO SAME PAGE
========================= */
redirectBack(["tasks_added" => $created]);
?>