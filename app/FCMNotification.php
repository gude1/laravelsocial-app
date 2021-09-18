<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class FCMNotification extends Model
{
    //

    /**
     * Public static function  to send a notification via firebase messaging
     *
     *
     */
    public static function send($data = [])
    {
        if (!$data || count($data) < 1) {
            return false;
        }

        $data = json_encode($data);
        $headers = [
            'Authorization: key=' . env('FIREBASE_SERVER_KEY', ''),
            'Content-Type: application/json',
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        return $response = curl_exec($ch);
    }
}
