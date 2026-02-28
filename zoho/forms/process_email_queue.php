<?php
/**
 * Email Queue Processor
 * This script should be run via cron job every minute to process pending emails
 * Add to crontab: * * * * * /usr/bin/php /path/to/process_email_queue.php
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/queue_processor.log');

// Include mail configuration
require_once __DIR__ . '/mail_config.php';

// Set script timeout to 30 seconds
set_time_limit(30);

// Log script start
error_log("=== EMAIL QUEUE PROCESSOR STARTED ===");
error_log("Timestamp: " . date('Y-m-d H:i:s'));

// Check mail server status
$mailServerStatus = checkMailServerStatus();

if (!$mailServerStatus) {
    error_log("ERROR: Mail server is not accessible. Skipping queue processing.");
    exit(1);
}

// Get queue status
$queueCount = getMailQueueStatus();
error_log("Queue status: $queueCount pending emails");

if ($queueCount > 0) {
    // Process mail queue
    processMailQueue();
    
    // Get updated queue status
    $remainingCount = getMailQueueStatus();
    $processedCount = $queueCount - $remainingCount;
    
    error_log("Processed: $processedCount emails");
    error_log("Remaining: $remainingCount emails");
} else {
    error_log("No pending emails in queue");
}

// Clean up old email files (older than 1 hour)
$emailQueueDir = __DIR__ . '/email_queue';
if (is_dir($emailQueueDir)) {
    $files = glob($emailQueueDir . '/*.json');
    $cleanedCount = 0;
    
    foreach ($files as $file) {
        $fileTime = filemtime($file);
        if ((time() - $fileTime) > 3600) { // 1 hour
            unlink($file);
            $cleanedCount++;
        }
    }
    
    if ($cleanedCount > 0) {
        error_log("Cleaned up $cleanedCount old email files");
    }
}

// Log script completion
error_log("=== EMAIL QUEUE PROCESSOR COMPLETED ===");
error_log("Timestamp: " . date('Y-m-d H:i:s'));
error_log("Memory usage: " . memory_get_peak_usage(true) . " bytes");
error_log("Execution time: " . (microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) . " seconds");

exit(0);
?> 