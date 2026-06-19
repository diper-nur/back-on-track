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

$message = "";

/* =========================
   CURRENT SUBJECT
========================= */
$current_subject = isset($_GET["subject_id"]) ? (int)$_GET["subject_id"] : 0;

/* =========================
   CHECK SUBJECT
========================= */
if ($current_subject > 0) {
    $stmt = $conn->prepare("
        SELECT id
        FROM subjects
        WHERE id = ?
        AND class_id = ?
        LIMIT 1
    ");

    $stmt->bind_param("ii", $current_subject, $class_id);
    $stmt->execute();

    $subjectExists = $stmt->get_result()->fetch_assoc();

    if (!$subjectExists) {
        $current_subject = 0;
        $message = "❌ " . t("invalid_subject_selected");
    }
}

/* =========================
   ADD SUBJECT
========================= */
if (isset($_POST["add_subject"])) {
    $name = trim($_POST["subject_name"] ?? "");

    if ($name === "") {
        $message = "❌ " . t("subject_name_empty");
    } else {
        $check = $conn->prepare("
            SELECT id
            FROM subjects
            WHERE class_id = ?
            AND LOWER(name) = LOWER(?)
            LIMIT 1
        ");

        $check->bind_param("is", $class_id, $name);
        $check->execute();

        $existing = $check->get_result()->fetch_assoc();

        if ($existing) {
            $message = "❌ " . t("subject_exists");
        } else {
            $stmt = $conn->prepare("
                INSERT INTO subjects (class_id, name)
                VALUES (?, ?)
            ");

            $stmt->bind_param("is", $class_id, $name);

            if ($stmt->execute()) {
                $message = "✅ " . t("subject_added");
            } else {
                $message = "❌ " . t("subject_error");
            }
        }
    }
}

/* =========================
   UPLOAD MATERIAL
========================= */
if (isset($_POST["upload"]) && $current_subject > 0) {
    $title = trim($_POST["title"] ?? "");

    if ($title === "") {
        $message = "❌ " . t("provide_title");
    } elseif (!isset($_FILES["files"]) || empty($_FILES["files"]["name"][0])) {
        $message = "❌ " . t("choose_file");
    } else {
        $files = $_FILES["files"];

        $upload_dir_fs = __DIR__ . "/assets/uploads/";
        $upload_dir_db = "assets/uploads/";

        if (!is_dir($upload_dir_fs)) {
            mkdir($upload_dir_fs, 0777, true);
        }

        if (!is_writable($upload_dir_fs)) {
            die("Upload folder is not writable: " . htmlspecialchars($upload_dir_fs));
        }

        $allowed = [
            "pdf",
            "docx",
            "pptx",
            "xlsx",
            "txt",
            "png",
            "jpg",
            "jpeg"
        ];

        $maxFileSize = 10 * 1024 * 1024;
        $uploadedCount = 0;
        $errors = [];

        $stmt = $conn->prepare("
            INSERT INTO materials (user_id, class_id, subject_id, title)
            VALUES (?, ?, ?, ?)
        ");

        $stmt->bind_param("iiis", $user_id, $class_id, $current_subject, $title);

        if ($stmt->execute()) {
            $material_id = $stmt->insert_id;

            for ($i = 0; $i < count($files["name"]); $i++) {
                $original_name = $files["name"][$i] ?? "";
                $tmp_name = $files["tmp_name"][$i] ?? "";
                $size = $files["size"][$i] ?? 0;
                $error = $files["error"][$i] ?? UPLOAD_ERR_NO_FILE;

                if ($original_name === "") {
                    continue;
                }

                if ($error !== UPLOAD_ERR_OK) {
                    $errors[] = htmlspecialchars($original_name) . " failed. PHP upload error code: " . $error;
                    continue;
                }

                $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

                if (!in_array($ext, $allowed, true)) {
                    $errors[] = htmlspecialchars($original_name) . " skipped. File type not allowed.";
                    continue;
                }

                if ($size <= 0) {
                    $errors[] = htmlspecialchars($original_name) . " skipped. Empty file.";
                    continue;
                }

                if ($size > $maxFileSize) {
                    $errors[] = htmlspecialchars($original_name) . " skipped. File is larger than 10MB.";
                    continue;
                }

                if (!is_uploaded_file($tmp_name)) {
                    $errors[] = htmlspecialchars($original_name) . " skipped. Invalid uploaded file.";
                    continue;
                }

                $safe_name = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($original_name));
                $file_name = uniqid("file_", true) . "_" . $safe_name;

                $path_fs = $upload_dir_fs . $file_name;
                $path_db = $upload_dir_db . $file_name;

                if (move_uploaded_file($tmp_name, $path_fs)) {
                    $stmt2 = $conn->prepare("
                        INSERT INTO material_files (material_id, file_path)
                        VALUES (?, ?)
                    ");

                    $stmt2->bind_param("is", $material_id, $path_db);

                    if ($stmt2->execute()) {
                        $uploadedCount++;
                    } else {
                        $errors[] = htmlspecialchars($original_name) . " uploaded but database insert failed.";
                    }
                } else {
                    $errors[] = htmlspecialchars($original_name) . " failed during move_uploaded_file().";
                }
            }

            if ($uploadedCount > 0) {
                $message = "✅ " . t("material_uploaded") . " " . $uploadedCount;

                if (!empty($errors)) {
                    $message .= "<br><span style='color:#b45309;'>" . implode("<br>", $errors) . "</span>";
                }
            } else {
                $delete = $conn->prepare("DELETE FROM materials WHERE id = ?");
                $delete->bind_param("i", $material_id);
                $delete->execute();

                $message = "❌ " . t("material_upload_failed");

                if (!empty($errors)) {
                    $message .= "<br>" . implode("<br>", $errors);
                }
            }
        } else {
            $message = "❌ " . t("failed_create_material");
        }
    }
}

/* =========================
   GET SUBJECTS
========================= */
$stmt = $conn->prepare("
    SELECT *
    FROM subjects
    WHERE class_id = ?
    ORDER BY name ASC
");

$stmt->bind_param("i", $class_id);
$stmt->execute();

$subjects = $stmt->get_result();
?>

<h2><?= t("study_materials") ?></h2>

<?php if ($message): ?>
    <p style="color:green;">
        <?= $message ?>
    </p>
<?php endif; ?>

<form method="POST" style="margin-bottom:15px;">
    <input
        type="text"
        name="subject_name"
        placeholder="<?= t('add_subject_placeholder') ?>"
        required
    >

    <button type="submit" name="add_subject">
        <?= t("add_subject") ?>
    </button>
</form>

<form method="GET">
    <select name="subject_id" onchange="this.form.submit()">
        <option value=""><?= t("select_subject") ?></option>

        <?php while ($s = $subjects->fetch_assoc()): ?>
            <option
                value="<?= (int)$s["id"] ?>"
                <?= $current_subject === (int)$s["id"] ? "selected" : "" ?>
            >
                <?= htmlspecialchars($s["name"]) ?>
            </option>
        <?php endwhile; ?>
    </select>
</form>

<hr>

<?php if ($current_subject > 0): ?>

    <h3><?= t("upload_material") ?></h3>

    <form method="POST" enctype="multipart/form-data">
        <input
            type="text"
            name="title"
            placeholder="<?= t('material_title') ?>"
            required
        >

        <input
            type="file"
            name="files[]"
            multiple
            required
            accept=".pdf,.docx,.pptx,.xlsx,.txt,.png,.jpg,.jpeg"
        >

        <button type="submit" name="upload">
            <?= t("upload") ?>
        </button>
    </form>

    <p style="color:#666;font-size:14px;">
        <?= t("supported_files") ?>
    </p>

    <hr>

    <h3><?= t("materials") ?></h3>

    <div class="materials-grid">

        <?php
        $stmt = $conn->prepare("
            SELECT *
            FROM materials
            WHERE class_id = ?
            AND subject_id = ?
            ORDER BY created_at DESC
        ");

        $stmt->bind_param("ii", $class_id, $current_subject);
        $stmt->execute();

        $materials = $stmt->get_result();

        if ($materials && $materials->num_rows > 0):

            while ($m = $materials->fetch_assoc()):

                $materialId = (int)$m["id"];

                $stmtFiles = $conn->prepare("
                    SELECT *
                    FROM material_files
                    WHERE material_id = ?
                ");

                $stmtFiles->bind_param("i", $materialId);
                $stmtFiles->execute();

                $files = $stmtFiles->get_result();

                $hasFiles = $files && $files->num_rows > 0;
                ?>

                <div class="material-card">

                    <h4><?= htmlspecialchars($m["title"]) ?></h4>

                    <?php if ($hasFiles): ?>

                        <?php while ($f = $files->fetch_assoc()): ?>

                            <?php
                            $filePath = $f["file_path"];
                            $fileName = basename($filePath);
                            ?>

                            <a href="<?= htmlspecialchars($filePath) ?>" target="_blank">
                                📄 <?= htmlspecialchars($fileName) ?>
                            </a><br>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <span style="color:#999;">⚠️ <?= t("no_files_attached") ?></span><br>

                    <?php endif; ?>

                    <small>
                        <?= t("uploaded") ?>: <?= htmlspecialchars($m["created_at"]) ?>
                    </small>

                    <br><br>

                    <?php if ($hasFiles): ?>

                        <form method="GET" action="material-detail.php" style="margin:0;">
                            <input type="hidden" name="id" value="<?= $materialId ?>">

                            <button
                                type="submit"
                                class="btn-ai"
                                onclick="this.innerText='<?= t('loading_ai') ?>'; this.disabled=true; this.form.submit();"
                            >
                                🤖 <?= t("study") ?>
                            </button>
                        </form>

                    <?php else: ?>

                        <span style="color:#999;">⚠️ <?= t("study_unavailable") ?></span>

                    <?php endif; ?>

                </div>

            <?php endwhile; ?>

        <?php else: ?>

            <p><?= t("no_materials") ?></p>

        <?php endif; ?>

    </div>

<?php endif; ?>

<?php include("includes/footer.php"); ?>