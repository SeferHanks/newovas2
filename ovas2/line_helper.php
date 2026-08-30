<?php
// ไฟล์: line_helper.php

// --------------------------------------------------------------------------------
// 1. แจ้งเตือนแอดมิน (เมื่อมีคนจองเข้ามา)
// --------------------------------------------------------------------------------
function sendLineBookingAlert($bookingData) {
    $accessToken = "DSJ7vfSG1H/DoW6aFMYcC1voEW/Fc7nN7ZEclgy5+lkHFDLxa3/frYIbaKcJmSYYnipA8Xw98WWfUJXZyz9AT4rhB2CIt8ubYcAuLOowZmd0I5/rdepCr0TZbe9kNBnkY9Z5e7KM7T+Vjlx0QB+UvgdB04t89/1O/w1cDnyilFU="; 
    $targetId = "C53982867c0bdbada2e8994a9e341b1fd"; 

    $approveUrl = "https://bamboo-thesaurus-abdominal.ngrok-free.dev/ovas2/mobile_approve.php?id=" . $bookingData['id'];

    $flexData = [
        "type" => "flex",
        "altText" => "🔔 มีรายการจองรถใหม่!",
        "contents" => [
            "type" => "bubble",
            "header" => [
                "type" => "box", "layout" => "vertical",
                "contents" => [
                    ["type" => "text", "text" => "รออนุมัติ (Pending)", "weight" => "bold", "color" => "#E6A23C", "size" => "xs"],
                    ["type" => "text", "text" => "รายการจองรถใหม่", "weight" => "bold", "size" => "xl", "margin" => "md"],
                    ["type" => "text", "text" => "Ref: BK-" . str_pad($bookingData['id'], 5, '0', STR_PAD_LEFT), "size" => "xs", "color" => "#aaaaaa", "margin" => "xs"]
                ]
            ],
            "body" => [
                "type" => "box", "layout" => "vertical",
                "contents" => [
                    [
                        "type" => "box", "layout" => "baseline", "margin" => "md", "contents" => [
                            ["type" => "text", "text" => "ผู้ขอ", "color" => "#aaaaaa", "size" => "sm", "flex" => 2],
                            ["type" => "text", "text" => $bookingData['fullname'], "wrap" => true, "color" => "#666666", "size" => "sm", "flex" => 5]
                        ]
                    ],
                    [
                        "type" => "box", "layout" => "baseline", "margin" => "md", "contents" => [
                            ["type" => "text", "text" => "ไปที่", "color" => "#aaaaaa", "size" => "sm", "flex" => 2],
                            ["type" => "text", "text" => $bookingData['destination'], "wrap" => true, "color" => "#666666", "size" => "sm", "flex" => 5]
                        ]
                    ],
                    [
                        "type" => "box", "layout" => "baseline", "margin" => "md", "contents" => [
                            ["type" => "text", "text" => "เวลา", "color" => "#aaaaaa", "size" => "sm", "flex" => 2],
                            ["type" => "text", "text" => $bookingData['date_range'], "wrap" => true, "color" => "#666666", "size" => "sm", "flex" => 5]
                        ]
                    ]
                ]
            ],
            "footer" => [
                "type" => "box", "layout" => "vertical", "spacing" => "sm",
                "contents" => [
                    [
                        "type" => "button", "style" => "primary", "height" => "sm", "color" => "#1E3C72",
                        "action" => ["type" => "uri", "label" => "ตรวจสอบ / อนุมัติ", "uri" => $approveUrl]
                    ]
                ]
            ]
        ]
    ];

    $payload = ["to" => $targetId, "messages" => [$flexData]];
    $ch = curl_init("https://api.line.me/v2/bot/message/push");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $accessToken
    ]);
    curl_exec($ch);
    curl_close($ch);
}

// --------------------------------------------------------------------------------
// 2. แจ้งเตือนคนขับรถ (ส่วนตัว)
// --------------------------------------------------------------------------------
function sendLineToDriver($driverLineId, $jobData) {
    $accessToken = "DSJ7vfSG1H/DoW6aFMYcC1voEW/Fc7nN7ZEclgy5+lkHFDLxa3/frYIbaKcJmSYYnipA8Xw98WWfUJXZyz9AT4rhB2CIt8ubYcAuLOowZmd0I5/rdepCr0TZbe9kNBnkY9Z5e7KM7T+Vjlx0QB+UvgdB04t89/1O/w1cDnyilFU="; 

    $flexData = [
        "type" => "flex",
        "altText" => "🚖 มีงานใหม่: " . $jobData['destination'],
        "contents" => [
            "type" => "bubble",
            "header" => [
                "type" => "box", "layout" => "vertical",
                "contents" => [
                    ["type" => "text", "text" => "งานใหม่ (อนุมัติแล้ว)", "weight" => "bold", "color" => "#198754", "size" => "xs"],
                    ["type" => "text", "text" => "ใบสั่งงานพนักงานขับรถ", "weight" => "bold", "size" => "lg", "margin" => "md"],
                    ["type" => "text", "text" => "Ref: BK-" . str_pad($jobData['id'], 5, '0', STR_PAD_LEFT), "size" => "xxs", "color" => "#aaaaaa"]
                ]
            ],
            "body" => [
                "type" => "box", "layout" => "vertical",
                "contents" => [
                    [
                        "type" => "box", "layout" => "vertical", "margin" => "md", "spacing" => "sm",
                        "contents" => [
                            ["type" => "text", "text" => "สถานที่ไป", "color" => "#aaaaaa", "size" => "xs"],
                            ["type" => "text", "text" => $jobData['destination'], "weight" => "bold", "size" => "md", "wrap" => true, "color" => "#333333"]
                        ]
                    ],
                    [
                        "type" => "box", "layout" => "vertical", "margin" => "md", "spacing" => "sm",
                        "contents" => [
                            ["type" => "text", "text" => "วัน-เวลา เดินทาง", "color" => "#aaaaaa", "size" => "xs"],
                            ["type" => "text", "text" => $jobData['date_range'], "size" => "sm", "wrap" => true, "color" => "#333333"]
                        ]
                    ],
                    [ "type" => "separator", "margin" => "lg" ],
                    [
                        "type" => "box", "layout" => "vertical", "margin" => "lg", "spacing" => "sm",
                        "contents" => [
                            ["type" => "text", "text" => "ยานพาหนะที่ใช้", "color" => "#aaaaaa", "size" => "xs"],
                            ["type" => "text", "text" => "" . $jobData['car_info'], "size" => "sm", "wrap" => true, "weight" => "bold", "color" => "#1e3c72"]
                        ]
                    ],
                    [
                        "type" => "box", "layout" => "vertical", "margin" => "md", "spacing" => "sm",
                        "contents" => [
                            ["type" => "text", "text" => "หมายเหตุ / ภารกิจ", "color" => "#aaaaaa", "size" => "xs"],
                            ["type" => "text", "text" => "" . $jobData['remark'], "size" => "sm", "wrap" => true, "color" => "#555555"]
                        ]
                    ],
                    [ "type" => "separator", "margin" => "lg" ],
                    [
                        "type" => "box", "layout" => "horizontal", "margin" => "lg", "contents" => [
                            [
                                "type" => "box", "layout" => "vertical", "flex" => 1,
                                "contents" => [
                                    ["type" => "text", "text" => "ผู้ขอใช้รถ", "color" => "#aaaaaa", "size" => "xs"],
                                    ["type" => "text", "text" => $jobData['user_name'], "size" => "sm", "wrap" => true]
                                ]
                            ],
                            [
                                "type" => "box", "layout" => "vertical", "flex" => 1,
                                "contents" => [
                                    ["type" => "text", "text" => "เบอร์โทร", "color" => "#aaaaaa", "size" => "xs"],
                                    ["type" => "text", "text" => $jobData['user_phone'], "size" => "sm", "color" => "#1e3c72", "decoration" => "underline", "action" => ["type" => "uri", "uri" => "tel:" . $jobData['user_phone']]]
                                ]
                            ]
                        ]
                    ]
                ]
            ], // <-- ปิดก้อน body ให้ถูกต้องเรียบร้อย
            "footer" => [
                "type" => "box", 
                "layout" => "vertical", 
                "spacing" => "sm",
                "contents" => [
                    // ปุ่มที่ 1: โทรหาผู้จอง
                    [
                        "type" => "button", 
                        "style" => "primary", 
                        "height" => "sm", 
                        "color" => "#198754",
                        "action" => ["type" => "uri", "label" => "📞 โทรหาผู้จอง", "uri" => "tel:" . $jobData['user_phone']]
                    ],
                    // ปุ่มที่ 2: บันทึกการเดินทาง
                    [
                        "type" => "button", 
                        "style" => "primary", 
                        "height" => "sm", 
                        "color" => "#1e3c72", 
                        "margin" => "sm",
                        "action" => [
                            "type" => "uri", 
                            "label" => "📝 บันทึกการเดินทาง", 
                            "uri" => "https://bamboo-thesaurus-abdominal.ngrok-free.dev/ovas2/driver_record.php?id=" . $jobData['id']
                        ]
                    ]
                ]
            ]
        ] // ปิด contents ใหญ่ของ bubble
    ]; // ปิดอาเรย์ $flexData

    $payload = ["to" => $driverLineId, "messages" => [$flexData]];
    $ch = curl_init("https://api.line.me/v2/bot/message/push");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $accessToken
    ]);
    curl_exec($ch);
    curl_close($ch);
}

// --------------------------------------------------------------------------------
// 3. แจ้งเตือนเข้า "กลุ่มไลน์" (อนุมัติ)
// --------------------------------------------------------------------------------
function sendApproveFlexToGroup($jobData) {
    $accessToken = "DSJ7vfSG1H/DoW6aFMYcC1voEW/Fc7nN7ZEclgy5+lkHFDLxa3/frYIbaKcJmSYYnipA8Xw98WWfUJXZyz9AT4rhB2CIt8ubYcAuLOowZmd0I5/rdepCr0TZbe9kNBnkY9Z5e7KM7T+Vjlx0QB+UvgdB04t89/1O/w1cDnyilFU="; 
    $groupId = "C8f6e513ced233b9b709519c1f3e8bc85"; 

    $passengers = (!empty($jobData['passengers'])) ? (string)$jobData['passengers'] : "-";
    $car_info = (!empty($jobData['car_info'])) ? (string)$jobData['car_info'] : "ไม่ระบุ";
    $driver_info = (!empty($jobData['driver_info'])) ? (string)$jobData['driver_info'] : "ไม่ระบุ";
    $user_name = (!empty($jobData['user_name'])) ? (string)$jobData['user_name'] : "ไม่ระบุ";
    $destination = (!empty($jobData['destination'])) ? (string)$jobData['destination'] : "-";
    $date_range = (!empty($jobData['date_range'])) ? (string)$jobData['date_range'] : "-";
    $bid = str_pad($jobData['id'], 5, '0', STR_PAD_LEFT);

    $flexData = [
        "type" => "flex",
        "altText" => "✅ อนุมัติการจอง: " . $user_name,
        "contents" => [
            "type" => "bubble",
            "size" => "mega",
            "header" => [
                "type" => "box",
                "layout" => "vertical",
                "contents" => [
                    [ "type" => "text", "text" => "อนุมัติการจองรถ (Approved)", "weight" => "bold", "color" => "#ffffff", "size" => "sm" ]
                ],
                "backgroundColor" => "#06c755",
                "paddingAll" => "md"
            ],
            "body" => [
                "type" => "box",
                "layout" => "vertical",
                "contents" => [
                    [ "type" => "text", "text" => "ผู้ขอใช้รถ:", "color" => "#aaaaaa", "size" => "xs" ],
                    [ "type" => "text", "text" => $user_name, "weight" => "bold", "size" => "xl", "color" => "#1e3c72", "wrap" => true ],
                    [ "type" => "separator", "margin" => "md" ],
                    
                    [
                        "type" => "box", "layout" => "vertical", "margin" => "md", "backgroundColor" => "#f8f9fa", "cornerRadius" => "md", "paddingAll" => "md",
                        "contents" => [
                            [
                                "type" => "box", "layout" => "baseline", "spacing" => "sm",
                                "contents" => [
                                    ["type" => "text", "text" => "สถานที่:", "color" => "#aaaaaa", "size" => "xs", "flex" => 2],
                                    ["type" => "text", "text" => $destination, "weight" => "bold", "size" => "sm", "wrap" => true, "color" => "#333333", "flex" => 5]
                                ]
                            ],
                            [
                                "type" => "box", "layout" => "baseline", "spacing" => "sm", "margin" => "sm",
                                "contents" => [
                                    ["type" => "text", "text" => "เวลา:", "color" => "#aaaaaa", "size" => "xs", "flex" => 2],
                                    ["type" => "text", "text" => $date_range, "size" => "sm", "wrap" => true, "color" => "#555555", "flex" => 5]
                                ]
                            ],
                            [
                                "type" => "box", "layout" => "baseline", "spacing" => "sm", "margin" => "sm",
                                "contents" => [
                                    ["type" => "text", "text" => "จำนวน:", "color" => "#aaaaaa", "size" => "xs", "flex" => 2],
                                    ["type" => "text", "text" => $passengers . " ท่าน", "size" => "sm", "wrap" => true, "color" => "#555555", "flex" => 5]
                                ]
                            ]
                        ]
                    ],

                    [
                        "type" => "box", "layout" => "vertical", "margin" => "lg", "spacing" => "sm",
                        "contents" => [
                            [
                                "type" => "box", "layout" => "baseline", "spacing" => "sm",
                                "contents" => [
                                    ["type" => "text", "text" => "รถ:", "color" => "#aaaaaa", "size" => "xs", "flex" => 1],
                                    ["type" => "text", "text" => $car_info, "size" => "xs", "color" => "#666666", "wrap" => true, "flex" => 4]
                                ]
                            ],
                            [
                                "type" => "box", "layout" => "baseline", "spacing" => "sm",
                                "contents" => [
                                    ["type" => "text", "text" => "คนขับ:", "color" => "#aaaaaa", "size" => "xs", "flex" => 1],
                                    ["type" => "text", "text" => $driver_info, "size" => "xs", "color" => "#666666", "wrap" => true, "flex" => 4]
                                ]
                            ]
                        ]
                    ],
                    [ "type" => "text", "text" => "Ref: BK-" . $bid, "size" => "xxs", "color" => "#cccccc", "align" => "center", "margin" => "lg" ]
                ]
            ]
        ]
    ];

    $payload = ["to" => $groupId, "messages" => [$flexData]];
    $jsonPayload = json_encode($payload);

    $ch = curl_init("https://api.line.me/v2/bot/message/push");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $accessToken
    ]);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
}

// --------------------------------------------------------------------------------
// 4. แจ้งเตือนเข้า "กลุ่มไลน์" (ไม่อนุมัติ)
// --------------------------------------------------------------------------------
function sendRejectFlexToGroup($jobData) {
    $accessToken = "DSJ7vfSG1H/DoW6aFMYcC1voEW/Fc7nN7ZEclgy5+lkHFDLxa3/frYIbaKcJmSYYnipA8Xw98WWfUJXZyz9AT4rhB2CIt8ubYcAuLOowZmd0I5/rdepCr0TZbe9kNBnkY9Z5e7KM7T+Vjlx0QB+UvgdB04t89/1O/w1cDnyilFU="; 
    $groupId = "C8f6e513ced233b9b709519c1f3e8bc85"; 

    $user_name = (!empty($jobData['user_name'])) ? (string)$jobData['user_name'] : "ไม่ระบุ";
    $destination = (!empty($jobData['destination'])) ? (string)$jobData['destination'] : "-";
    $reason = (!empty($jobData['reason'])) ? (string)$jobData['reason'] : "-";
    $bid = str_pad($jobData['id'], 5, '0', STR_PAD_LEFT);
    
    $date_range = (!empty($jobData['date_range'])) ? (string)$jobData['date_range'] : "-";
    $car_info = (!empty($jobData['car_info'])) ? (string)$jobData['car_info'] : "-";

    $flexData = [
        "type" => "flex",
        "altText" => "❌ ไม่อนุมัติรายการ: " . $user_name,
        "contents" => [
            "type" => "bubble",
            "size" => "mega",
            "header" => [
                "type" => "box", "layout" => "vertical",
                "contents" => [
                    [ "type" => "text", "text" => "ไม่อนุมัติ (Rejected)", "weight" => "bold", "color" => "#ffffff", "size" => "sm" ]
                ],
                "backgroundColor" => "#dc3545", "paddingAll" => "md"
            ],
            "body" => [
                "type" => "box", "layout" => "vertical",
                "contents" => [
                    [ "type" => "text", "text" => "ผู้ขอใช้รถ:", "color" => "#aaaaaa", "size" => "xs" ],
                    [ "type" => "text", "text" => $user_name, "weight" => "bold", "size" => "xl", "color" => "#333333", "wrap" => true ],
                    [ "type" => "separator", "margin" => "md" ],
                    
                    [
                        "type" => "box", "layout" => "vertical", "margin" => "md", "spacing" => "sm",
                        "contents" => [
                            [
                                "type" => "box", "layout" => "baseline", "spacing" => "sm",
                                "contents" => [
                                    ["type" => "text", "text" => "สถานที่:", "color" => "#aaaaaa", "size" => "xs", "flex" => 2],
                                    ["type" => "text", "text" => $destination, "weight" => "bold", "size" => "sm", "wrap" => true, "color" => "#333333", "flex" => 5]
                                ]
                            ],
                            [
                                "type" => "box", "layout" => "baseline", "spacing" => "sm", "margin" => "sm",
                                "contents" => [
                                    ["type" => "text", "text" => "เวลา:", "color" => "#aaaaaa", "size" => "xs", "flex" => 2],
                                    ["type" => "text", "text" => $date_range, "size" => "sm", "wrap" => true, "color" => "#555555", "flex" => 5]
                                ]
                            ],
                            [
                                "type" => "box", "layout" => "baseline", "spacing" => "sm", "margin" => "sm",
                                "contents" => [
                                    ["type" => "text", "text" => "รถ:", "color" => "#aaaaaa", "size" => "xs", "flex" => 2],
                                    ["type" => "text", "text" => $car_info, "size" => "sm", "wrap" => true, "color" => "#555555", "flex" => 5]
                                ]
                            ]
                        ]
                    ],

                    [
                        "type" => "box", "layout" => "vertical", "margin" => "md", "backgroundColor" => "#f8d7da", "cornerRadius" => "md", "paddingAll" => "md",
                        "contents" => [
                            [ "type" => "text", "text" => "เหตุผลที่ไม่อนุมัติ:", "color" => "#842029", "size" => "xs", "weight" => "bold" ],
                            [ "type" => "text", "text" => $reason, "size" => "sm", "wrap" => true, "color" => "#842029", "margin" => "sm" ]
                        ]
                    ],
                    
                    [ "type" => "text", "text" => "Ref: BK-" . $bid, "size" => "xxs", "color" => "#cccccc", "align" => "center", "margin" => "lg" ]
                ]
            ]
        ]
    ];

    $payload = ["to" => $groupId, "messages" => [$flexData]];
    $jsonPayload = json_encode($payload);

    $ch = curl_init("https://api.line.me/v2/bot/message/push");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $accessToken
    ]);
    
    curl_exec($ch);
    curl_close($ch);
}
?>