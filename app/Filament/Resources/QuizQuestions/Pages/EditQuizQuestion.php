<?php

namespace App\Filament\Resources\QuizQuestions\Pages;

use App\Filament\Resources\QuizQuestions\QuizQuestionResource;
use Filament\Resources\Pages\EditRecord;

class EditQuizQuestion extends EditRecord
{
    protected static string $resource = QuizQuestionResource::class;
}
