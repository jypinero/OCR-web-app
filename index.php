<?php
require_once __DIR__ . '/vendor/autoload.php';

use thiagoalessio\TesseractOCR\TesseractOCR;

$extractedTexts = [];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear'])) {
    $extractedTexts = [];
    $error = '';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['images']) && isset($_FILES['images']['tmp_name']) && is_array($_FILES['images']['tmp_name'])) {
        $imageTmpPaths = $_FILES['images']['tmp_name'];
        $fileNames = $_FILES['images']['name'];
        $language = 'eng';
        $fileTypes = array_map('mime_content_type', $imageTmpPaths);
        $extractedTexts = [];
        $errors = [];

        foreach ($imageTmpPaths as $index => $imageTmpPath) {
            $fileType = $fileTypes[$index];
            $fileName = $fileNames[$index];
            $isPdf = $fileType === 'application/pdf' || strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) === 'pdf';
            try {
                if ($isPdf) {
                    $outputImage = tempnam(sys_get_temp_dir(), 'ocr') . '.png';
                    $convertCmd = "/opt/homebrew/bin/magick -density 300 " . escapeshellarg($imageTmpPath) . "[0] -colorspace Gray -sharpen 0x1 -background white -alpha remove " . escapeshellarg($outputImage);
                    exec($convertCmd . ' 2>&1', $out, $ret);
                    if (!file_exists($outputImage) || filesize($outputImage) === 0) {
                        throw new Exception('Failed to convert PDF to image for OCR. Details: ' . implode("\n", $out));
                    }
                    $imageForOcr = $outputImage;
                } else {
                    $outputImage = tempnam(sys_get_temp_dir(), 'ocr') . '.png';
                    $convertCmd = "/opt/homebrew/bin/magick " . escapeshellarg($imageTmpPath) . " -colorspace Gray -sharpen 0x1 -background white -alpha remove " . escapeshellarg($outputImage);
                    exec($convertCmd . ' 2>&1', $out, $ret);
                    if (!file_exists($outputImage) || filesize($outputImage) === 0) {
                        throw new Exception('Failed to preprocess image for OCR. Details: ' . implode("\n", $out));
                    }
                    $imageForOcr = $outputImage;
                }
                $ocr = new TesseractOCR($imageForOcr);
                $ocr->lang($language);
                $ocr->executable('/opt/homebrew/bin/tesseract');
                $ocr->psm(3);
                $text = $ocr->run();
                $extractedTexts[] = [
                    'filename' => $fileName,
                    'text' => $text
                ];
                if (isset($outputImage) && file_exists($outputImage)) {
                    unlink($outputImage);
                }
            } catch (Exception $e) {
                $errors[] = 'Error processing file ' . htmlspecialchars($fileName) . ': ' . $e->getMessage();
            }
        }
        if (!empty($errors)) {
            $error = implode("\n", $errors);
        }
    } else {
        $error = 'Please upload at least one valid image or PDF file.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OCR Extractor</title>
    <!-- Bootstrap 5.3 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #e0e7ff 0%, #f8fafc 100%);
            font-family: 'Inter', 'Segoe UI', Arial, sans-serif;
        }
        .modern-card {
            border-radius: 1.5rem;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.12);
            background: rgba(255,255,255,0.95);
            animation: fadeInUp 0.7s cubic-bezier(.39,.575,.565,1) both;
        }
        @keyframes fadeInUp {
            0% { opacity: 0; transform: translateY(40px); }
            100% { opacity: 1; transform: none; }
        }
        .modern-title {
            font-size: 2.2rem;
            font-weight: 700;
            letter-spacing: -1px;
            color: #3730a3;
        }
        .modern-btn {
            background: linear-gradient(90deg, #6366f1 0%, #60a5fa 100%);
            border: none;
            color: #fff;
            font-weight: 600;
            transition: box-shadow 0.2s, transform 0.2s;
            box-shadow: 0 2px 8px rgba(99,102,241,0.08);
        }
        .modern-btn:hover, .modern-btn:focus {
            background: linear-gradient(90deg, #60a5fa 0%, #6366f1 100%);
            color: #fff;
            transform: translateY(-2px) scale(1.03);
            box-shadow: 0 4px 16px rgba(99,102,241,0.13);
        }
        .modern-textarea {
            background: #f1f5f9;
            border-radius: 1rem;
            border: 1.5px solid #c7d2fe;
            font-size: 1.1rem;
            color: #1e293b;
            padding: 1.1rem;
            min-height: 200px;
            box-shadow: 0 1px 4px rgba(99,102,241,0.04);
        }
        .modern-label {
            font-weight: 500;
            color: #6366f1;
        }
        .modern-card .card-body {
            padding: 2.2rem 2rem 2rem 2rem;
        }
        @media (max-width: 576px) {
            .modern-card .card-body { padding: 1.2rem; }
            .modern-title { font-size: 1.4rem; }
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center align-items-stretch">
            <div class="col-12 col-md-8 col-lg-12 mb-4">
                <div class="card modern-card">
                    <div class="card-body">
                        <h2 class="modern-title mb-4 text-center">Image/PDF to Text (English Only)</h2>
                        <form action="" method="post" enctype="multipart/form-data" id="ocrForm">
                            <div class="mb-3">
                                <label for="image" class="form-label modern-label">Select image or PDF to upload:</label>
                                <input type="file" class="form-control" name="images[]" id="image" accept="image/*,.pdf" multiple required>
                            </div>
                            <button type="submit" class="btn modern-btn w-100">Extract Text Here</button>
                            <div id="typingAnimation" class="text-primary mt-3" style="display:none; font-weight: 500;">
                                Extracting<span id="typingDots"></span>
                            </div>
                        </form>
                        <br>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="clear" value="1">
                            <button type="submit" class="btn btn-outline-secondary">Clear</button>
                        </form>
                        <?php if ($error): ?>
                            <div class="alert alert-danger mt-4" role="alert">
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php if (!empty($extractedTexts)): ?>
                <?php foreach ($extractedTexts as $idx => $result): ?>
                    <div class="col-12 col-md-6 col-lg-4 mb-4 d-flex align-items-stretch">
                        <div class="card modern-card h-100 w-100">
                            <div class="card-body d-flex flex-column h-100">
                                <h5 class="card-title modern-label mb-2">Extracted Text: <span class="fw-normal text-secondary"><?php echo htmlspecialchars($result['filename']); ?></span></h5>
                                <textarea class="form-control modern-textarea" id="extractedText<?php echo $idx; ?>" style="height:200px; overflow-y:auto; resize:vertical; visibility:hidden; min-height:120px;" spellcheck="true"><?php echo htmlspecialchars($result['text']); ?></textarea>
                                <div class="mt-3">
                                    <button class="btn modern-btn me-2 savePdfBtn" data-textarea="extractedText<?php echo $idx; ?>">Save as PDF</button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jsPDF CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script>
    function autoResizeTextarea(id) {
        var textarea = document.getElementById(id);
        if (!textarea) return;
        textarea.style.height = 'auto';
        textarea.style.height = textarea.scrollHeight + 'px';
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
        });
    }
    // Typing animation for extracting
    window.addEventListener('DOMContentLoaded', function() {
        // autoResizeTextarea('extractedText'); // Removed auto-resize
        // Typing animation for each extracted text
        <?php if (!empty($extractedTexts)): ?>
        <?php foreach ($extractedTexts as $idx => $result): ?>
        (function() {
            var extractedTextArea = document.getElementById('extractedText<?php echo $idx; ?>');
            if (extractedTextArea) {
                var fullText = extractedTextArea.value;
                extractedTextArea.value = '';
                extractedTextArea.style.visibility = 'visible';
                var i = 0;
                var typingSpeed = 10;
                function typeChar() {
                    if (i <= fullText.length) {
                        extractedTextArea.value = fullText.substring(0, i);
                        i++;
                        setTimeout(typeChar, typingSpeed);
                    }
                }
                typeChar();
            }
        })();
        <?php endforeach; ?>
        <?php endif; ?>
        // Save as PDF for each card
        var saveBtns = document.querySelectorAll('.savePdfBtn');
        saveBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                var textareaId = btn.getAttribute('data-textarea');
                var text = document.getElementById(textareaId).value;
                var doc = new window.jspdf.jsPDF();
                doc.setFont('Helvetica');
                doc.setFontSize(12);
                var pageHeight = doc.internal.pageSize.getHeight();
                var margin = 20;
                var y = margin;
                var lineHeight = 7;
                var lines = doc.splitTextToSize(text, 180);
                lines.forEach(function(line) {
                    if (y + lineHeight > pageHeight - margin) {
                        doc.addPage();
                        y = margin;
                    }
                    doc.text(line, 10, y);
                    y += lineHeight;
                });
                doc.save('extracted-text.pdf');
            });
        });
    });
    </script>
</body>
</html>