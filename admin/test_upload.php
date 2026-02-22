<?php
// test_upload.php - Place this in your admin folder
echo "<h2>PHP Upload Configuration</h2>";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "post_max_size: " . ini_get('post_max_size') . "<br>";
echo "max_execution_time: " . ini_get('max_execution_time') . " seconds<br>";
echo "max_input_time: " . ini_get('max_input_time') . " seconds<br>";
echo "memory_limit: " . ini_get('memory_limit') . "<br>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h3>Upload Result:</h3>";
    echo "<pre>";
    print_r($_FILES);
    echo "</pre>";
    
    if ($_FILES['test_file']['error'] == 0) {
        $upload_dir = __DIR__ . '/../uploads/test/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $destination = $upload_dir . $_FILES['test_file']['name'];
        if (move_uploaded_file($_FILES['test_file']['tmp_name'], $destination)) {
            echo "<span style='color: green; font-weight: bold;'>✓ Upload successful! File saved to: uploads/test/" . $_FILES['test_file']['name'] . "</span>";
        } else {
            echo "<span style='color: red; font-weight: bold;'>✗ Failed to move uploaded file</span>";
        }
    } else {
        $upload_errors = [
            1 => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
            2 => 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form',
            3 => 'The uploaded file was only partially uploaded',
            4 => 'No file was uploaded',
            6 => 'Missing a temporary folder',
            7 => 'Failed to write file to disk',
            8 => 'A PHP extension stopped the file upload'
        ];
        
        $error_code = $_FILES['test_file']['error'];
        $error_msg = isset($upload_errors[$error_code]) ? $upload_errors[$error_code] : 'Unknown upload error (code: ' . $error_code . ')';
        echo "<span style='color: red; font-weight: bold;'>✗ Upload Error: " . $error_msg . "</span>";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>File Upload Test</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; }
        .info { background: #f0f0f0; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        form { background: white; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
        input[type=file] { margin: 10px 0; }
        button { background: #4CAF50; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
        button:hover { background: #45a049; }
    </style>
</head>
<body>
    <div class="container">
        <h1>File Upload Test</h1>
        
        <div class="info">
            <h3>Current PHP Configuration:</h3>
            <ul>
                <li><strong>upload_max_filesize:</strong> <?= ini_get('upload_max_filesize') ?></li>
                <li><strong>post_max_size:</strong> <?= ini_get('post_max_size') ?></li>
                <li><strong>max_execution_time:</strong> <?= ini_get('max_execution_time') ?> seconds</li>
                <li><strong>memory_limit:</strong> <?= ini_get('memory_limit') ?></li>
            </ul>
        </div>
        
        <form method="POST" enctype="multipart/form-data">
            <h3>Select a file to upload:</h3>
            <input type="file" name="test_file" required>
            <br>
            <button type="submit">Test Upload</button>
        </form>
        
        <p style="margin-top: 20px; color: #666;">
            <small>Try uploading a small file first, then try your 200MB MP4 file</small>
        </p>
    </div>
</body>
</html>