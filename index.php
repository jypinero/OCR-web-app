<?php
require_once __DIR__ . '/vendor/autoload.php';

use thiagoalessio\TesseractOCR\TesseractOCR;

$extractedText = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear'])) {
    $extractedText = '';
    $error = '';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $imageTmpPath = $_FILES['image']['tmp_name'];
        $language = 'eng';
        $fileType = mime_content_type($imageTmpPath);
        $isPdf = $fileType === 'application/pdf' || strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION)) === 'pdf';

        try {
            if ($isPdf) {
                // Convert first page of PDF to PNG
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
            $ocr->executable('/opt/homebrew/bin/tesseract'); // Adjust path if needed
            $ocr->psm(3); // 3 = Fully automatic page segmentation, but no OSD. Try other values (e.g., 6, 11) for different layouts.
            $extractedText = $ocr->run();

            // Clean up temp image if created
            if (isset($outputImage) && file_exists($outputImage)) {
                unlink($outputImage);
            }
        } catch (Exception $e) {
            $error = 'Error processing image: ' . $e->getMessage();
        }
    } else {
        $error = 'Please upload a valid image or PDF file.';
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
        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-6 mb-4">
                <div class="card modern-card">
                    <div class="card-body">
                        <h2 class="modern-title mb-4 text-center">Image/PDF to Text (English Only)</h2>
                        <form action="" method="post" enctype="multipart/form-data" id="ocrForm">
                            <div class="mb-3">
                                <label for="image" class="form-label modern-label">Select image or PDF to upload:</label>
                                <input type="file" class="form-control" name="image" id="image" accept="image/*,.pdf" required>
                            </div>
                            <button type="submit" class="btn modern-btn w-100">Extract Text Here</button>
                            <div id="typingAnimation" class="text-primary mt-3" style="display:none; font-weight: 500;">
                                Extracting<span id="typingDots"></span>
                            </div>
                        </form>
                        <?php if ($error): ?>
                            <div class="alert alert-danger mt-4" role="alert">
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php if ($extractedText): ?>
            <div class="col-12 col-md-10 col-lg-8">
                <div class="card modern-card">
                    <div class="card-body">
                        <h5 class="card-title modern-label">Extracted Text:</h5>
                        <textarea class="form-control modern-textarea" id="extractedText" style="overflow:hidden; resize:none; visibility:hidden;" spellcheck="true"><?php echo htmlspecialchars($extractedText); ?></textarea>
                        <button id="savePdfBtn" class="btn modern-btn mt-3 me-2">Save as PDF</button>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="clear" value="1">
                            <button type="submit" class="btn btn-outline-secondary mt-3">Clear</button>
                        </form>
                    </div>
                </div>
            </div>
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
        autoResizeTextarea('extractedText');
        var saveBtn = document.getElementById('savePdfBtn');
        if (saveBtn) {
            saveBtn.addEventListener('click', function() {
                var text = document.getElementById('extractedText').value;
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
        }
        // Typing animation logic
        var form = document.getElementById('ocrForm');
        var typingAnimation = document.getElementById('typingAnimation');
        var typingDots = document.getElementById('typingDots');
        var interval;
        if (form) {
            form.addEventListener('submit', function() {
                if (typingAnimation) {
                    typingAnimation.style.display = 'block';
                    var dots = 0;
                    interval = setInterval(function() {
                        dots = (dots + 1) % 4;
                        typingDots.textContent = '.'.repeat(dots);
                    }, 500);
                }
            });
        }
        // Hide animation if result is shown
        if (typingAnimation && document.getElementById('extractedText')) {
            typingAnimation.style.display = 'none';
            if (interval) clearInterval(interval);
        }
        // Typing animation for extracted text
        var extractedTextArea = document.getElementById('extractedText');
        if (extractedTextArea) {
            var fullText = extractedTextArea.value;
            extractedTextArea.value = '';
            extractedTextArea.style.visibility = 'visible';
            var i = 0;
            var typingSpeed = 1; // ms per character, adjust for speed
            function typeChar() {
                if (i <= fullText.length) {
                    extractedTextArea.value = fullText.substring(0, i);
                    i++;
                    setTimeout(typeChar, typingSpeed);
                }
            }
            typeChar();
        }
        var clearBtn = document.getElementById('clearTextBtn');
        if (clearBtn && extractedTextArea) {
            clearBtn.addEventListener('click', function() {
                extractedTextArea.value = '';
            });
        }
    });
    </script>
</body>
</html>