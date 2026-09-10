<?php
declare(strict_types=1);
namespace App;

final class TrackedSMTP extends \PHPMailer\PHPMailer\SMTP
{
    public bool $dataStarted = false;
    public function data($msg_data)
    {
        $this->dataStarted = true;
        return parent::data($msg_data);
    }
}
