<?php
/**
 * PHPMailer Setup (Shared)
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../../PHPMailer/src/SMTP.php';

// ── SMTP Configuration ───────────────────────────────────────
// Replace with your email credentials
define('MAIL_HOST',     'smtp.gmail.com');
define('MAIL_PORT',     587);
define('MAIL_USERNAME', 'parinitapaigwar@gmail.com');      // Your Gmail
define('MAIL_PASSWORD', 'axcn huty bqqz elfz');         // Gmail App Password
define('MAIL_FROM',     'parinitapaigwar@gmail.com');
define('MAIL_FROM_NAME','CareerFlow - Placement Cell');

function getMailer() {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = MAIL_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = MAIL_USERNAME;
    $mail->Password   = MAIL_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = MAIL_PORT;
    $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
    $mail->isHTML(true);
    return $mail;
}

function sendOTPEmail($toEmail, $toName, $otp) {
    try {
        $mail = getMailer();
        $mail->addAddress($toEmail, $toName);
        $mail->Subject = 'Password Reset OTP - CareerFlow';
        $mail->Body    = "
        <div style='font-family:Arial,sans-serif;max-width:500px;margin:auto;padding:30px;border:1px solid #e0e0e0;border-radius:8px;'>
            <h2 style='color:#4f46e5;'>Password Reset</h2>
            <p>Hello <b>$toName</b>,</p>
            <p>Your OTP for password reset is:</p>
            <div style='font-size:36px;font-weight:bold;color:#4f46e5;letter-spacing:10px;text-align:center;padding:20px;background:#f5f3ff;border-radius:8px;margin:20px 0;'>$otp</div>
            <p>This OTP is valid for <b>10 minutes</b>.</p>
            <p>If you did not request this, please ignore this email.</p>
            <hr>
            <small style='color:#888;'>CareerFlow | Placement Cell</small>
        </div>";
        $mail->AltBody = "Your OTP is: $otp (valid for 10 minutes)";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mail Error: " . $e->getMessage());
        return false;
    }
}

function sendInterviewNotificationEmail($toEmail, $toName, $company, $round, $date, $time, $venue) {
    try {
        $mail = getMailer();
        $mail->addAddress($toEmail, $toName);
        $mail->Subject = "Interview Scheduled - $company";
        $baseUrl = 'http://localhost/CareerFlow/backend';
        $mail->Body    = "
        <div style='font-family:Arial,sans-serif;max-width:500px;margin:auto;padding:30px;border:1px solid #e0e0e0;border-radius:8px;'>
            <h2 style='color:#4f46e5;'>Interview Scheduled</h2>
            <p>Dear <b>$toName</b>,</p>
            <p>Your interview has been scheduled. Details below:</p>
            <table style='width:100%;border-collapse:collapse;margin:15px 0;'>
                <tr><td style='padding:8px;font-weight:bold;'>Company</td><td>$company</td></tr>
                <tr><td style='padding:8px;font-weight:bold;'>Round</td><td>$round</td></tr>
                <tr><td style='padding:8px;font-weight:bold;'>Date</td><td>$date</td></tr>
                <tr><td style='padding:8px;font-weight:bold;'>Time</td><td>$time</td></tr>
                <tr><td style='padding:8px;font-weight:bold;'>Venue</td><td>$venue</td></tr>
            </table>
            <p>Please log in to confirm your attendance.</p>
            <a href='$baseUrl/login.php' style='background:#4f46e5;color:#fff;padding:10px 20px;text-decoration:none;border-radius:5px;'>Login Now</a>
        </div>";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mail Error: " . $e->getMessage());
        return false;
    }
}

function sendApplicationStatusEmail($toEmail, $toName, $jobTitle, $company, $status) {
    try {
        $mail = getMailer();
        $mail->addAddress($toEmail, $toName);
        $mail->Subject = "Application Update - $jobTitle at $company";
        $color = $status === 'Selected' ? '#16a34a' : ($status === 'Rejected' ? '#dc2626' : '#4f46e5');
        $baseUrl = 'http://localhost/CareerFlow/backend';
        $mail->Body    = "
        <div style='font-family:Arial,sans-serif;max-width:500px;margin:auto;padding:30px;border:1px solid #e0e0e0;border-radius:8px;'>
            <h2 style='color:$color;'>Application $status</h2>
            <p>Dear <b>$toName</b>,</p>
            <p>Your application for <b>$jobTitle</b> at <b>$company</b> has been updated to: 
            <span style='color:$color;font-weight:bold;'>$status</span></p>
            <a href='$baseUrl/login.php' style='background:#4f46e5;color:#fff;padding:10px 20px;text-decoration:none;border-radius:5px;'>View Details</a>
        </div>";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mail Error: " . $e->getMessage());
        return false;
    }
}
