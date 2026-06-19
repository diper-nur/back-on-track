<?php
include("includes/auth.php");
include("config/db.php");
include("includes/lang.php");
include("includes/header.php");
include("includes/navbar.php");
include("config/env.php");

require_once __DIR__ . "/vendor/autoload.php";

use Smalot\PdfParser\Parser;

/* =========================
   SETTINGS
========================= */
$DEBUG_EXTRACTION = false;
$MAX_AI_CHARS = 30000;
$OPENAI_ENDPOINT = "https://api.openai.com/v1/chat/completions";

/* =========================
   BASIC DATA
========================= */
$material_id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

if ($material_id <= 0) {
    die("Invalid material ID.");
}

$user_id = $_SESSION["user_id"] ?? 0;
$class_id = $_SESSION["class_id"] ?? 0;

if (!$user_id) {
    die("Unauthorized.");
}

/*
   IMPORTANT:
   This prevents the whole website from freezing while OCR/OpenAI is working.
*/
session_write_close();

/* =========================
   AUTO ADD CACHE COLUMNS
========================= */
function ensureMaterialCacheColumns($conn) {
    $existing = [];

    $result = $conn->query("SHOW COLUMNS FROM materials");

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $existing[] = $row["Field"];
        }
    }

    $needed = [
        "extracted_text" => "LONGTEXT NULL",
        "ai_output" => "LONGTEXT NULL",
        "extraction_debug" => "LONGTEXT NULL",
        "ai_generated_at" => "DATETIME NULL"
    ];

    foreach ($needed as $column => $definition) {
        if (!in_array($column, $existing)) {
            $conn->query("ALTER TABLE materials ADD `$column` $definition");
        }
    }
}

ensureMaterialCacheColumns($conn);

/* =========================
   GET MATERIAL
========================= */
$stmt = $conn->prepare("
    SELECT *
    FROM materials
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $material_id);
$stmt->execute();

$material = $stmt->get_result()->fetch_assoc();

if (!$material) {
    die("Material not found.");
}

$forceRefresh = isset($_GET["refresh_ai"]);

/* =========================
   HELPERS
========================= */
function resolveFilePath($dbPath) {
    if (!$dbPath) {
        return false;
    }

    $dbPath = str_replace("\\", "/", $dbPath);
    $dbPath = str_replace("\0", "", $dbPath);
    $dbPath = ltrim($dbPath, "/");

    $projectRoot = realpath(__DIR__);
    $documentRoot = realpath($_SERVER["DOCUMENT_ROOT"] ?? "");

    $possiblePaths = [
        $projectRoot . "/" . $dbPath,
        $documentRoot . "/" . $dbPath,
        $documentRoot . "/backontrack/" . $dbPath,
        $documentRoot . "/BackOnTrack/" . $dbPath,
        dirname($projectRoot) . "/" . $dbPath
    ];

    foreach ($possiblePaths as $path) {
        $real = realpath($path);

        if ($real && file_exists($real) && is_file($real)) {
            return $real;
        }
    }

    return false;
}

function cleanExtractedText($text) {
    if (!$text) {
        return "";
    }

    $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, "UTF-8");
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = preg_replace('/[ \t]+/', ' ', $text);
    $text = preg_replace('/\n[ \t]+/', "\n", $text);
    $text = preg_replace('/\n{3,}/', "\n\n", $text);

    return trim($text);
}

function safeSubstr($text, $start, $length) {
    if (function_exists("mb_substr")) {
        return mb_substr($text, $start, $length, "UTF-8");
    }

    return substr($text, $start, $length);
}

function shellAvailable() {
    return function_exists("shell_exec");
}

function commandExists($command) {
    if (!shellAvailable()) {
        return false;
    }

    if (file_exists($command)) {
        return true;
    }

    if (strpos($command, "\\") !== false || strpos($command, "/") !== false) {
        return false;
    }

    $check = shell_exec("where " . $command . " 2>NUL");

    return !empty(trim((string)$check));
}

function findCommand($candidates) {
    foreach ($candidates as $candidate) {
        if (commandExists($candidate)) {
            return $candidate;
        }
    }

    return false;
}

function quoteCommand($command) {
    if (file_exists($command)) {
        return '"' . $command . '"';
    }

    return $command;
}

/* =========================
   OCR
========================= */
function runTesseractOnImage($imagePath, &$error) {
    if (!shellAvailable()) {
        $error .= "shell_exec is disabled. Cannot run Tesseract OCR.\n";
        return "";
    }

    $tesseract = findCommand([
        "C:\\Program Files\\Tesseract-OCR\\tesseract.exe",
        "C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe",
        "tesseract"
    ]);

    if (!$tesseract) {
        $error .= "Tesseract not found.\n";
        return "";
    }

    $command =
        quoteCommand($tesseract) .
        " " .
        escapeshellarg($imagePath) .
        " stdout --psm 6 2>&1";

    return cleanExtractedText(shell_exec($command));
}

/* =========================
   PDF EXTRACTOR
========================= */
function extractPdfText($realPath, $parser, &$method, &$error) {
    $text = "";

    try {
        $pdf = $parser->parseFile($realPath);
        $text = cleanExtractedText($pdf->getText());

        if (strlen($text) > 50) {
            $method = "Smalot PDF Parser";
            return $text;
        }
    } catch (Throwable $e) {
        $error .= "Smalot failed: " . $e->getMessage() . "\n";
    }

    if (!shellAvailable()) {
        $error .= "shell_exec is disabled. Cannot use Poppler or OCR.\n";
        return "";
    }

    $pdftotext = findCommand([
        "C:\\poppler\\Library\\bin\\pdftotext.exe",
        "C:\\Program Files\\poppler\\Library\\bin\\pdftotext.exe",
        "C:\\Program Files\\Poppler\\Library\\bin\\pdftotext.exe",
        "pdftotext"
    ]);

    if ($pdftotext) {
        $tempTxt = tempnam(sys_get_temp_dir(), "pdftext_") . ".txt";

        $command =
            quoteCommand($pdftotext) .
            " -layout " .
            escapeshellarg($realPath) .
            " " .
            escapeshellarg($tempTxt) .
            " 2>&1";

        $output = shell_exec($command);

        if (file_exists($tempTxt)) {
            $text = cleanExtractedText(file_get_contents($tempTxt));
            unlink($tempTxt);

            if (strlen($text) > 50) {
                $method = "Poppler pdftotext";
                return $text;
            }
        }

        $error .= "pdftotext produced no readable text. Output: " . trim((string)$output) . "\n";
    } else {
        $error .= "pdftotext not found.\n";
    }

    $pdftoppm = findCommand([
        "C:\\poppler\\Library\\bin\\pdftoppm.exe",
        "C:\\Program Files\\poppler\\Library\\bin\\pdftoppm.exe",
        "C:\\Program Files\\Poppler\\Library\\bin\\pdftoppm.exe",
        "pdftoppm"
    ]);

    if (!$pdftoppm) {
        $error .= "pdftoppm not found. Cannot convert scanned PDF pages to images.\n";
        return "";
    }

    $tempBase = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "pdf_ocr_" . uniqid();

    $convertCommand =
        quoteCommand($pdftoppm) .
        " -png -r 220 -f 1 -l 8 " .
        escapeshellarg($realPath) .
        " " .
        escapeshellarg($tempBase) .
        " 2>&1";

    $convertOutput = shell_exec($convertCommand);
    $images = glob($tempBase . "-*.png");

    if (!$images || count($images) === 0) {
        $error .= "pdftoppm created no images. Output: " . trim((string)$convertOutput) . "\n";
        return "";
    }

    $ocrText = "";

    foreach ($images as $imagePath) {
        $ocrText .= "\n" . runTesseractOnImage($imagePath, $error);

        if (file_exists($imagePath)) {
            unlink($imagePath);
        }
    }

    $ocrText = cleanExtractedText($ocrText);

    if (strlen($ocrText) > 50) {
        $method = "OCR: pdftoppm + Tesseract";
        return $ocrText;
    }

    $error .= "OCR ran but extracted less than 50 characters.\n";

    return "";
}

/* =========================
   DOCX EXTRACTOR
========================= */
function extractDocxImagesWithOCR($zip, &$error) {
    $ocrText = "";
    $tempFiles = [];

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);

        if (!preg_match('/^word\/media\/.*\.(png|jpg|jpeg)$/i', $name)) {
            continue;
        }

        $imageData = $zip->getFromIndex($i);

        if (!$imageData) {
            continue;
        }

        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $tempImage = tempnam(sys_get_temp_dir(), "docx_img_") . "." . $ext;

        file_put_contents($tempImage, $imageData);
        $tempFiles[] = $tempImage;

        $ocrText .= "\n" . runTesseractOnImage($tempImage, $error);
    }

    foreach ($tempFiles as $file) {
        if (file_exists($file)) {
            unlink($file);
        }
    }

    return cleanExtractedText($ocrText);
}

function extractDocxText($realPath, &$method, &$error) {
    if (!class_exists("ZipArchive")) {
        $error = "ZipArchive is not enabled. Enable extension=zip in C:\\xampp\\php\\php.ini.";
        $method = "DOCX failed: ZipArchive missing";
        return "";
    }

    $zip = new ZipArchive;

    if ($zip->open($realPath) !== true) {
        $error = "Could not open DOCX as ZIP.";
        $method = "DOCX failed: cannot open ZIP";
        return "";
    }

    $textParts = [];
    $checkedFiles = [];

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);

        if (!preg_match('/^word\/.*\.xml$/', $name)) {
            continue;
        }

        $xml = $zip->getFromIndex($i);

        if (!$xml) {
            continue;
        }

        $checkedFiles[] = $name;

        if (preg_match_all('/<w:t[^>]*>(.*?)<\/w:t>/s', $xml, $matches)) {
            foreach ($matches[1] as $match) {
                $textParts[] = html_entity_decode(strip_tags($match), ENT_QUOTES | ENT_XML1, "UTF-8");
            }
        }

        if (preg_match_all('/<a:t[^>]*>(.*?)<\/a:t>/s', $xml, $matches)) {
            foreach ($matches[1] as $match) {
                $textParts[] = html_entity_decode(strip_tags($match), ENT_QUOTES | ENT_XML1, "UTF-8");
            }
        }

        $fallbackXml = str_replace(
            ["</w:p>", "</w:tr>", "</w:tc>", "</a:p>", "</a:t>"],
            ["\n", "\n", "\t", "\n", " "],
            $xml
        );

        $fallback = html_entity_decode(strip_tags($fallbackXml), ENT_QUOTES | ENT_XML1, "UTF-8");

        if (strlen(trim($fallback)) > 20) {
            $textParts[] = $fallback;
        }
    }

    $text = cleanExtractedText(implode("\n", $textParts));

    $lines = preg_split('/\r\n|\r|\n/', $text);
    $uniqueLines = [];

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line !== "" && !in_array($line, $uniqueLines)) {
            $uniqueLines[] = $line;
        }
    }

    $text = cleanExtractedText(implode("\n", $uniqueLines));

    if (strlen($text) > 50) {
        $zip->close();
        $method = "DOCX full XML scan";
        return $text;
    }

    $ocrText = extractDocxImagesWithOCR($zip, $error);
    $zip->close();

    if (strlen($ocrText) > 50) {
        $method = "DOCX embedded image OCR";
        return $ocrText;
    }

    $method = "DOCX scanned but no text";
    $error .= "No readable text found inside DOCX XML files. Checked files: " . implode(", ", $checkedFiles);

    return "";
}

/* =========================
   PPTX / XLSX / IMAGE
========================= */
function extractPptxText($realPath, &$method, &$error) {
    if (!class_exists("ZipArchive")) {
        $error = "ZipArchive is not enabled.";
        return "";
    }

    $zip = new ZipArchive;

    if ($zip->open($realPath) !== true) {
        $error = "Could not open PPTX as ZIP.";
        return "";
    }

    $text = "";

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);

        if (preg_match('/^ppt\/slides\/slide\d+\.xml$/', $name)) {
            $xml = $zip->getFromIndex($i);

            if (!$xml) {
                continue;
            }

            $xml = str_replace("</a:p>", "\n", $xml);
            $xml = str_replace("</a:t>", " ", $xml);

            $text .= "\n" . strip_tags($xml);
        }
    }

    $zip->close();

    $text = cleanExtractedText($text);

    if (strlen($text) > 20) {
        $method = "PPTX ZipArchive";
    } else {
        $error = "No readable text found in PPTX slides.";
    }

    return $text;
}

function extractXlsxText($realPath, &$method, &$error) {
    if (!class_exists("ZipArchive")) {
        $error = "ZipArchive is not enabled.";
        return "";
    }

    $zip = new ZipArchive;

    if ($zip->open($realPath) !== true) {
        $error = "Could not open XLSX as ZIP.";
        return "";
    }

    $text = "";

    $sharedStrings = $zip->getFromName("xl/sharedStrings.xml");

    if ($sharedStrings) {
        $sharedStrings = str_replace("</si>", "\n", $sharedStrings);
        $sharedStrings = str_replace("</t>", " ", $sharedStrings);
        $text .= "\n" . strip_tags($sharedStrings);
    }

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);

        if (preg_match('/^xl\/worksheets\/sheet\d+\.xml$/', $name)) {
            $xml = $zip->getFromIndex($i);

            if (!$xml) {
                continue;
            }

            $xml = str_replace("</row>", "\n", $xml);
            $xml = str_replace("</c>", " ", $xml);

            $text .= "\n" . strip_tags($xml);
        }
    }

    $zip->close();

    $text = cleanExtractedText($text);

    if (strlen($text) > 20) {
        $method = "XLSX ZipArchive";
    } else {
        $error = "No readable text found in XLSX.";
    }

    return $text;
}

function extractImageText($realPath, &$method, &$error) {
    $ocrText = runTesseractOnImage($realPath, $error);

    if (strlen($ocrText) > 50) {
        $method = "Image OCR: Tesseract";
        return $ocrText;
    }

    $method = "Image OCR failed";
    $error .= "Tesseract ran but extracted less than 50 characters.";

    return "";
}

function extractTextFromFile($realPath, $parser) {
    $method = "none";
    $error = "";

    if (!$realPath || !file_exists($realPath)) {
        return [
            "text" => "",
            "method" => "none",
            "error" => "File not found."
        ];
    }

    $ext = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));

    try {
        if ($ext === "pdf") {
            $text = extractPdfText($realPath, $parser, $method, $error);
        } elseif ($ext === "txt") {
            $text = cleanExtractedText(file_get_contents($realPath));
            $method = "TXT file_get_contents";
        } elseif ($ext === "docx") {
            $text = extractDocxText($realPath, $method, $error);
        } elseif ($ext === "pptx") {
            $text = extractPptxText($realPath, $method, $error);
        } elseif ($ext === "xlsx") {
            $text = extractXlsxText($realPath, $method, $error);
        } elseif (in_array($ext, ["jpg", "jpeg", "png"])) {
            $text = extractImageText($realPath, $method, $error);
        } else {
            return [
                "text" => "",
                "method" => "unsupported",
                "error" => "Unsupported file type: " . $ext
            ];
        }

        return [
            "text" => cleanExtractedText($text),
            "method" => $method,
            "error" => trim($error)
        ];

    } catch (Throwable $e) {
        return [
            "text" => "",
            "method" => "exception",
            "error" => $e->getMessage()
        ];
    }
}

/* =========================
   GET FILES
========================= */
$stmt = $conn->prepare("
    SELECT *
    FROM material_files
    WHERE material_id = ?
");

$stmt->bind_param("i", $material_id);
$stmt->execute();

$result = $stmt->get_result();

$filesList = [];

while ($f = $result->fetch_assoc()) {
    $filesList[] = $f;
}

/* =========================
   EXTRACT TEXT WITH DATABASE CACHE
========================= */
$fileText = "";
$debugInfo = [];

$cachedText = trim($material["extracted_text"] ?? "");

if (!$forceRefresh && strlen($cachedText) >= 50) {
    $fileText = $cachedText;

    $debugInfo[] = [
        "source" => "database extracted_text cache",
        "characters" => strlen($fileText)
    ];
} else {
    $parser = new Parser();

    foreach ($filesList as $f) {
        $dbPath = $f["file_path"];
        $realPath = resolveFilePath($dbPath);

        $extraction = extractTextFromFile($realPath, $parser);
        $text = $extraction["text"];

        $debugInfo[] = [
            "db_path" => $dbPath,
            "real_path" => $realPath ? $realPath : "NOT FOUND",
            "exists" => ($realPath && file_exists($realPath)) ? "YES" : "NO",
            "size" => ($realPath && file_exists($realPath)) ? filesize($realPath) : 0,
            "extension" => strtolower(pathinfo($dbPath, PATHINFO_EXTENSION)),
            "extraction_method" => $extraction["method"],
            "extracted_characters" => strlen(trim($text)),
            "shell_exec_enabled" => shellAvailable() ? "YES" : "NO",
            "zip_enabled" => class_exists("ZipArchive") ? "YES" : "NO",
            "error" => $extraction["error"]
        ];

        if (strlen(trim($text)) > 20) {
            $fileText .= "\n\n==============================\n";
            $fileText .= "FILE: " . basename($dbPath) . "\n";
            $fileText .= "EXTRACTION METHOD: " . $extraction["method"] . "\n";
            $fileText .= "==============================\n\n";
            $fileText .= trim($text);
        }
    }

    $fileText = cleanExtractedText($fileText);

    if (strlen($fileText) >= 50) {
        $debugJson = json_encode($debugInfo, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $stmt = $conn->prepare("
            UPDATE materials
            SET extracted_text = ?, extraction_debug = ?
            WHERE id = ?
        ");

        $stmt->bind_param("ssi", $fileText, $debugJson, $material_id);
        $stmt->execute();
    }
}

/* =========================
   AI WITH DATABASE CACHE
========================= */
$ai_output = null;
$cachedAi = trim($material["ai_output"] ?? "");

if (!$forceRefresh && strlen($cachedAi) >= 20 && strlen($fileText) >= 50) {
    $ai_output = $cachedAi;
} elseif (strlen($fileText) < 50) {
    $ai_output = "❌ No readable text was extracted from the uploaded file.";
} else {
    $apiKey = $_ENV["OPENAI_API_KEY"] ?? "";

    if (!$apiKey) {
        $ai_output = "❌ Missing OpenAI API key.";
    } else {
        $safeFileText = safeSubstr($fileText, 0, $MAX_AI_CHARS);

        $systemPrompt = "
You are a strict academic study assistant.

Rules:
- Use ONLY the uploaded material text.
- Do NOT give generic study advice.
- Do NOT invent facts.
- Do NOT mention topics not present in the uploaded text.
- Every paragraph must refer to concrete content from the uploaded text.
- Use specific terms, names, numbers, headings, examples, or claims from the uploaded text.
- The final section heading must be exactly: LEARNING TASKS
";

        $userPrompt = "
UPLOADED MATERIAL TEXT:
-----------------------
$safeFileText
-----------------------

Analyze this exact uploaded material.

Required output:

1. MATERIAL OVERVIEW
2. MAIN IDEAS
3. KEY TERMS
4. IMPORTANT DETAILS
5. STUDENT UNDERSTANDING

LEARNING TASKS
Write exactly 5 numbered learning tasks.
Each task must be directly based on the uploaded material.
";

        $payload = [
            "model" => "gpt-4o-mini",
            "temperature" => 0.1,
            "messages" => [
                ["role" => "system", "content" => $systemPrompt],
                ["role" => "user", "content" => $userPrompt]
            ]
        ];

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $OPENAI_ENDPOINT,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "Authorization: Bearer " . $apiKey
            ],
            CURLOPT_TIMEOUT => 90
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $ai_output = "❌ cURL Error: " . curl_error($ch);
        } else {
            $data = json_decode($response, true);

            if ($httpCode < 200 || $httpCode >= 300) {
                $errorMessage = $data["error"]["message"] ?? $response;
                $ai_output = "❌ OpenAI API Error: " . $errorMessage;
            } elseif (isset($data["error"])) {
                $ai_output = "❌ OpenAI API Error: " . $data["error"]["message"];
            } else {
                $ai_output = $data["choices"][0]["message"]["content"] ?? "❌ AI failed to generate a response.";
            }
        }

        curl_close($ch);

        if (strpos($ai_output, "❌") !== 0) {
            $stmt = $conn->prepare("
                UPDATE materials
                SET ai_output = ?, ai_generated_at = NOW()
                WHERE id = ?
            ");

            $stmt->bind_param("si", $ai_output, $material_id);
            $stmt->execute();
        }
    }
}

/* =========================
   TASK PARSING
========================= */
$ai_tasks = [];
$insideTasks = false;

$lines = preg_split('/\r\n|\r|\n/', $ai_output);

foreach ($lines as $line) {
    $line = trim($line);

    if ($line === "") {
        continue;
    }

    if (stripos($line, "LEARNING TASKS") !== false) {
        $insideTasks = true;
        continue;
    }

    if ($insideTasks && preg_match('/^\d+[\.\)]\s*(.+)$/', $line, $matches)) {
        $task = trim($matches[1]);

        if (strlen($task) > 5) {
            $ai_tasks[] = $task;
        }
    }
}

$ai_tasks = array_slice($ai_tasks, 0, 5);
?>

<div class="page-shell material-detail-page">

    <div class="page-hero">
        <h2><?= htmlspecialchars($material["title"]) ?></h2>
        <p>AI-powered deep analysis of your uploaded material</p>

        <a href="material-detail.php?id=<?= (int)$material_id ?>&refresh_ai=1" style="font-size:14px;">
            Regenerate AI
        </a>
    </div>

    <section class="detail-card">
        <h3>Files</h3>

        <?php if (!empty($filesList)): ?>
            <?php foreach ($filesList as $f): ?>
                <a href="<?= htmlspecialchars($f["file_path"]) ?>" target="_blank">
                    📄 <?= htmlspecialchars(basename($f["file_path"])) ?>
                </a><br>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No files attached to this material.</p>
        <?php endif; ?>
    </section>

    <?php if ($DEBUG_EXTRACTION): ?>
        <section class="detail-card">
            <h3>Debug: File Extraction</h3>

            <pre style="background:#111;color:#0f0;padding:15px;white-space:pre-wrap;font-size:13px;"><?php
echo "DEBUG FILE EXTRACTION\n\n";
print_r($debugInfo);

echo "\n\nEXTRACTED TEXT LENGTH:\n";
echo strlen($fileText);

echo "\n\nEXTRACTED TEXT PREVIEW:\n";
echo htmlspecialchars(substr($fileText, 0, 3000));
            ?></pre>
        </section>
    <?php endif; ?>

    <section class="detail-card">
        <h3>AI Explanation</h3>

        <div style="white-space:pre-wrap;">
            <?= htmlspecialchars($ai_output) ?>
        </div>
    </section>

    <?php if (!empty($ai_tasks)): ?>

        <section class="detail-card">
            <h3>AI Tasks</h3>

            <form method="POST" action="actions/create-task.php">

                <input type="hidden" name="material_id" value="<?= (int)$material_id ?>">
                <input type="hidden" name="subject_id" value="<?= (int)$material["subject_id"] ?>">
                <input type="hidden" name="description" value="AI-generated task from uploaded study material">
                <input type="hidden" name="notes" value="Generated from study material: <?= htmlspecialchars($material["title"]) ?>">
                <input type="hidden" name="deadline" value="<?= date('Y-m-d', strtotime('+1 day')) ?>">

                <?php foreach ($ai_tasks as $task): ?>
                    <label>
                        <input
                            type="checkbox"
                            name="selected_tasks[]"
                            value="<?= htmlspecialchars($task) ?>"
                            checked
                        >
                        <?= htmlspecialchars($task) ?>
                    </label><br>
                <?php endforeach; ?>

                <button type="submit" name="add_tasks" value="1">Add Tasks</button>

            </form>
        </section>

    <?php endif; ?>

</div>

<?php include("includes/footer.php"); ?>