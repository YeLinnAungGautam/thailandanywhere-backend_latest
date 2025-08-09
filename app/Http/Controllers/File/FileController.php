<?php

namespace App\Http\Controllers\File;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    private array $allowedTypes = ['export', 'pdfs'];

    public function countFiles(string $type): JsonResponse
    {
        try {
            // Validate file type
            if (!in_array($type, $this->allowedTypes)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid directory type'
                ], 400);
            }

            // Get all files in directory
            $files = Storage::files($type);
            $count = count($files);

            // Get directory size
            $totalSize = 0;
            foreach ($files as $file) {
                $totalSize += Storage::size($file);
            }

            return response()->json([
                'success' => true,
                'directory' => $type,
                'file_count' => $count,
                'total_size' => $totalSize,
                'total_size_mb' => round($totalSize / 1024 / 1024, 2)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error counting files: ' . $e->getMessage()
            ], 500);
        }
    }

    public function listFiles(string $type): JsonResponse
    {
        try {
            if (!in_array($type, $this->allowedTypes)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid directory type'
                ], 400);
            }

            $files = Storage::files($type);
            $fileDetails = [];

            foreach ($files as $file) {
                $fileDetails[] = [
                    'name' => basename($file),
                    'path' => $file,
                    'size' => Storage::size($file),
                    'size_mb' => round(Storage::size($file) / 1024 / 1024, 2),
                    'last_modified' => Storage::lastModified($file),
                    'formatted_date' => date('Y-m-d H:i:s', Storage::lastModified($file))
                ];
            }

            return response()->json([
                'success' => true,
                'directory' => $type,
                'file_count' => count($fileDetails),
                'files' => $fileDetails
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error listing files: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getStorageStats(): JsonResponse
    {
        try {
            $stats = [];
            $totalFiles = 0;
            $totalSize = 0;

            foreach ($this->allowedTypes as $type) {
                if (Storage::exists($type)) {
                    $files = Storage::files($type);
                    $directorySize = 0;

                    foreach ($files as $file) {
                        $directorySize += Storage::size($file);
                    }

                    $stats[$type] = [
                        'file_count' => count($files),
                        'size_bytes' => $directorySize,
                        'size_mb' => round($directorySize / 1024 / 1024, 2),
                        'size_gb' => round($directorySize / 1024 / 1024 / 1024, 3)
                    ];

                    $totalFiles += count($files);
                    $totalSize += $directorySize;
                } else {
                    $stats[$type] = [
                        'file_count' => 0,
                        'size_bytes' => 0,
                        'size_mb' => 0,
                        'size_gb' => 0
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'directories' => $stats,
                'totals' => [
                    'total_files' => $totalFiles,
                    'total_size_bytes' => $totalSize,
                    'total_size_mb' => round($totalSize / 1024 / 1024, 2),
                    'total_size_gb' => round($totalSize / 1024 / 1024 / 1024, 3)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error getting storage stats: ' . $e->getMessage()
            ], 500);
        }
    }

    // Advanced method with filtering
    public function getFilteredFiles(Request $request, string $type): JsonResponse
    {
        try {
            if (!in_array($type, $this->allowedTypes)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid directory type'
                ], 400);
            }

            $files = Storage::files($type);
            $fileDetails = [];

            // Filter parameters
            $extension = $request->get('extension');
            $minSize = $request->get('min_size', 0);
            $maxSize = $request->get('max_size');
            $dateFrom = $request->get('date_from');
            $dateTo = $request->get('date_to');

            foreach ($files as $file) {
                $fileInfo = [
                    'name' => basename($file),
                    'path' => $file,
                    'size' => Storage::size($file),
                    'size_mb' => round(Storage::size($file) / 1024 / 1024, 2),
                    'last_modified' => Storage::lastModified($file),
                    'formatted_date' => date('Y-m-d H:i:s', Storage::lastModified($file)),
                    'extension' => pathinfo($file, PATHINFO_EXTENSION)
                ];

                // Apply filters
                if ($extension && $fileInfo['extension'] !== $extension) {
                    continue;
                }

                if ($fileInfo['size'] < $minSize) {
                    continue;
                }

                if ($maxSize && $fileInfo['size'] > $maxSize) {
                    continue;
                }

                if ($dateFrom && Storage::lastModified($file) < strtotime($dateFrom)) {
                    continue;
                }

                if ($dateTo && Storage::lastModified($file) > strtotime($dateTo)) {
                    continue;
                }

                $fileDetails[] = $fileInfo;
            }

            return response()->json([
                'success' => true,
                'directory' => $type,
                'file_count' => count($fileDetails),
                'files' => $fileDetails,
                'filters_applied' => [
                    'extension' => $extension,
                    'min_size' => $minSize,
                    'max_size' => $maxSize,
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error getting filtered files: ' . $e->getMessage()
            ], 500);
        }
    }

    public function deleteFile(string $type, string $filename): JsonResponse
    {
        try {
            // Validate file type
            if (!in_array($type, $this->allowedTypes)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid file type'
                ], 400);
            }

            $filePath = "{$type}/{$filename}";

            // Check if file exists
            if (!Storage::exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found'
                ], 404);
            }

            // Security check - prevent directory traversal
            if (str_contains($filename, '..') || str_contains($filename, '/')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid filename'
                ], 400);
            }

            // Delete the file
            Storage::delete($filePath);

            return response()->json([
                'success' => true,
                'message' => "File deleted successfully from {$type}"
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting file: ' . $e->getMessage()
            ], 500);
        }
    }
}
