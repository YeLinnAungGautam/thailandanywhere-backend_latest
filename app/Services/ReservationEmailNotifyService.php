<?php
namespace App\Services;

class ReservationEmailNotifyService
{
    public static function saveAttachToTemp($attachments)
    {
        $attach_files = [];

        if (isset($attachments)) {
            foreach ($attachments as $attachment) {
                $attach_files[] = uploadFile($attachment, '/temp_files/attachments/');
            }
        }

        return $attach_files;
    }
}
