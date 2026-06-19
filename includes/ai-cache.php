<?php

function getAiCache($conn, $cacheKey) {
    $stmt = $conn->prepare("
        SELECT response_text
        FROM ai_cache
        WHERE cache_key = ?
        LIMIT 1
    ");

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("s", $cacheKey);
    $stmt->execute();

    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    return $row["response_text"] ?? null;
}

function saveAiCache($conn, $cacheKey, $cacheType, $sourceId, $responseText) {
    $stmt = $conn->prepare("
        INSERT INTO ai_cache
        (cache_key, cache_type, source_id, response_text)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            response_text = VALUES(response_text),
            updated_at = CURRENT_TIMESTAMP
    ");

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("ssis", $cacheKey, $cacheType, $sourceId, $responseText);

    return $stmt->execute();
}

?>
