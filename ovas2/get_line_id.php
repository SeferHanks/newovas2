<?php
// ไฟล์: get_line_id.php

// 1. ใส่ Channel Access Token ของคุณ
$accessToken = "DSJ7vfSG1H/DoW6aFMYcC1voEW/Fc7nN7ZEclgy5+lkHFDLxa3/frYIbaKcJmSYYnipA8Xw98WWfUJXZyz9AT4rhB2CIt8ubYcAuLOowZmd0I5/rdepCr0TZbe9kNBnkY9Z5e7KM7T+Vjlx0QB+UvgdB04t89/1O/w1cDnyilFU="; 

// รับค่าที่ LINE ส่งมา
$content = file_get_contents('php://input');
$events = json_decode($content, true);

// --- [Debug] บันทึกทุกอย่างลงไฟล์ log_hook.txt ---
file_put_contents("log_hook.txt", date("Y-m-d H:i:s") . " - Received: " . $content . "\n", FILE_APPEND);

if (!is_null($events['events'])) {
    foreach ($events['events'] as $event) {
        
        // ตรวจสอบว่าเป็นข้อความ หรือ การเข้าร่วมกลุ่ม
        if ($event['type'] == 'message' || $event['type'] == 'join' || $event['type'] == 'memberJoined') {
            
            $replyToken = $event['replyToken'];
            $source = $event['source'];
            $replyText = "";
            $userIdLog = "";

            // --- ตรวจสอบ ID ---
            if ($source['type'] == 'group') {
                $id = $source['groupId'];
                $replyText = "📢 Group ID: " . $id;
                $userIdLog = "Group ID: " . $id;
            } elseif ($source['type'] == 'room') {
                $id = $source['roomId'];
                $replyText = "📢 Room ID: " . $id;
                $userIdLog = "Room ID: " . $id;
            } else {
                $id = $source['userId'];
                $replyText = "👤 User ID: " . $id;
                $userIdLog = "User ID: " . $id;
            }

            // --- [Debug] บันทึก ID ที่ได้ลงไฟล์ log_id.txt (เผื่อบอทไม่ตอบ) ---
            file_put_contents("log_id.txt", date("Y-m-d H:i:s") . " - Found " . $userIdLog . "\n", FILE_APPEND);

            // เตรียมข้อความตอบกลับ
            $messages = [
                'type' => 'text',
                'text' => $replyText
            ];

            // ยิงกลับไปหาคนส่ง
            $url = 'https://api.line.me/v2/bot/message/reply';
            $data = [
                'replyToken' => $replyToken,
                'messages' => [$messages],
            ];
            
            $post = json_encode($data);
            $headers = array('Content-Type: application/json', 'Authorization: Bearer ' . $accessToken);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            // สำหรับ InfinityFree
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
            
            $result = curl_exec($ch);
            curl_close($ch);
        }
    }
}
echo "OK";
?>