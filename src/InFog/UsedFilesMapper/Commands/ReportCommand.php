<?php

namespace InFog\UsedFilesMapper\Commands;

class ReportCommand
{
    private $path = '';

    private $logsFile = '';

    private $outputFile = '';

    private $ignoreVendor = true;

    private $basePath = '';

    public function __construct($path, $basePath, $logsFile, $outputFile = '', $ignoreVendor = true)
    {
        $this->path = $path;
        $this->basePath = $basePath;
        $this->logsFile = $logsFile;
        $this->outputFile = $outputFile;
        $this->ignoreVendor = $ignoreVendor;
    }

    public function run()
    {
        $usedFiles = $this->fetchUsedFiles();
        $allFiles = $this->fetchAllFiles();

        foreach ($usedFiles as $fileName => $usage) {
            if (! in_array($fileName, $allFiles)) {
                unset($usedFiles[$fileName]);
            }
        }

        foreach ($allFiles as $k => $fileName) {
            if (isset($usedFiles[$fileName])) {
                unset($allFiles[$k]);
            }
        }

        $report = $this->generateReport($allFiles, $usedFiles);

        if ($this->outputFile != '') {
            file_put_contents($this->outputFile, $report);
        } else {
            echo $report;
        }

        return 0;
    }

    private function fetchUsedFiles()
    {
        if (! is_file($this->logsFile)) {
            throw new \Exception("The provided logs file does not exist: {$this->logsFile}" . PHP_EOL);
        }

        $usedFiles = array();

        $basePathLength = strlen($this->basePath);

        $handle = fopen($this->logsFile, 'r');

        while (($fileName = fgets($handle)) !== false) {
            $fileName = trim($fileName);

            if (strpos($fileName, $this->basePath) !== 0) {
                continue;
            }

            $fileName = trim(substr($fileName, $basePathLength), '/');

            if (strpos($fileName, $this->basePath) !== false) {
                continue;
            }

            if ($this->ignoreVendor && strpos($fileName, 'vendor/') === 0) {
                continue;
            }

            if (isset($usedFiles[$fileName])) {
                $usedFiles[$fileName]++;
            } else {
                $usedFiles[$fileName] = 1;
            }
        }

        fclose($handle);

        arsort($usedFiles);

        return $usedFiles;
    }

    private function fetchAllFiles()
    {
        $getVendor = '';

        if ($this->ignoreVendor) {
            $getVendor = ' | grep -v "vendor/"';
        }

        $pathLength = strlen($this->path);

        $allFiles = array();
        $files = array();

        exec("find {$this->path} -name '*.php' {$getVendor}", $files);

        foreach ($files as $fileName) {
            $fileName = substr($fileName, $pathLength);
            $fileName = trim($fileName, '/');
            $allFiles[] = $fileName;
        }

        return $allFiles;
    }

    public function generateReport($unusedFiles, $usedFiles)
    {
        $totalUsedFiles = count($usedFiles);
        $totalUnusedFiles = count($unusedFiles);
        $totalAllFiles = $totalUsedFiles + $totalUnusedFiles;
        $generatedOn = date('Y-m-d H:i:s');

        $highestUsage = reset($usedFiles);

        $backgroundColors = array();

        foreach ($usedFiles as $usage) {
            $backgroundColors[$usage] = (((int) (($usage * 255) / $highestUsage)) - 255) * -1;
        }

        $usagePercentage = number_format(($totalUsedFiles * 100) / $totalAllFiles, 2);

        ob_start();
        require __DIR__ . "/../resources/templates/report.php";
        $content = ob_get_contents();
        ob_end_clean();

        return $content;
    }
}
