<?php

class FileUpload {
    private $uploadDir = __DIR__ . '/../../uploads/';

    public function uploadImage($file) {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ["success" => false, "message" => "No valid image uploaded"];
        }

        $filename = basename($file['name']);
        $targetFile = $this->uploadDir . $filename;

        // Make sure the uploads directory exists
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }

        if (move_uploaded_file($file['tmp_name'], $targetFile)) {
            return [
                "success" => true,
                "file_path" => str_replace(__DIR__ . '/../../', '', $targetFile)
            ];
        } else {
            return ["success" => false, "message" => "Failed to move uploaded file"];
        }
    }
}
