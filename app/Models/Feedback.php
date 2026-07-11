<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Feedback extends Model
{
    /** Tên bảng tường minh — Laravel mặc định đoán 'feedback' (số ít). */
    protected $table = 'feedbacks';

    protected $fillable = [
        'name',
        'phone',
        'email',
        'content',
        'status',
        'reply_content',
        'replied_at',
    ];

    protected $casts = [
        'replied_at' => 'datetime',
    ];
}
