<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RagFeedback extends Model
{
    protected $table = 'rag_feedbacks';
    
    protected $fillable = [
        'query',
        'document_id',
        'rating',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'rating' => 'integer',
    ];

    /**
     * Relação com Document
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
