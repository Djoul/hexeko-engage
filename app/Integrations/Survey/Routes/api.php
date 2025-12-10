<?php

use App\Integrations\Survey\Http\Controllers\Me\AnswerController as MeAnswerController;
use App\Integrations\Survey\Http\Controllers\Me\SubmissionController as MeSubmissionController;
use App\Integrations\Survey\Http\Controllers\Me\SurveyController as MeSurveyController;
use App\Integrations\Survey\Http\Controllers\Me\ThemeController as MeThemeController;
use App\Integrations\Survey\Http\Controllers\QuestionController;
use App\Integrations\Survey\Http\Controllers\QuestionnaireController;
use App\Integrations\Survey\Http\Controllers\QuestionnaireQuestionController;
use App\Integrations\Survey\Http\Controllers\SurveyController;
use App\Integrations\Survey\Http\Controllers\SurveyMetricController;
use App\Integrations\Survey\Http\Controllers\SurveyQuestionController;
use App\Integrations\Survey\Http\Controllers\SurveyUserController;
use App\Integrations\Survey\Http\Controllers\ThemeController;
use App\Integrations\Survey\Http\Controllers\ThemeQuestionController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'api/v1'], function (): void {
    Route::prefix('survey')->middleware(['api', 'auth.cognito', 'check.active.financer', 'check.permission', 'check.allowed.financer'])
        ->name('survey.')->group(function (): void {
            Route::get('surveys/metrics', [SurveyMetricController::class, 'index'])->name('surveys.metrics.index');
            Route::get('surveys/{survey}', [SurveyController::class, 'show'])
                ->withoutMiddleware('can:view,survey')
                ->name('surveys.show');
            Route::apiResource('surveys', SurveyController::class)->except(['show']);
            Route::post('surveys/draft', [SurveyController::class, 'draft'])->name('surveys.draft');
            Route::post('surveys/{survey}/archive', [SurveyController::class, 'archive'])->name('surveys.archive');
            Route::post('surveys/{survey}/unarchive', [SurveyController::class, 'unarchive'])->name('surveys.unarchive');
            Route::get('surveys/{survey}/metrics', [SurveyMetricController::class, 'show'])->name('surveys.metrics.show');
            Route::get('surveys/{survey}/questions', [SurveyQuestionController::class, 'index'])->name('surveys.questions.index');
            Route::post('surveys/{survey}/questions/link', [SurveyQuestionController::class, 'link'])->name('surveys.questions.link');
            Route::post('surveys/{survey}/questions/unlink', [SurveyQuestionController::class, 'unlink'])->name('surveys.questions.unlink');
            Route::post('surveys/{survey}/questions/reorder', [SurveyQuestionController::class, 'reorder'])->name('surveys.questions.reorder');
            Route::get('surveys/{survey}/users', [SurveyUserController::class, 'index'])->name('surveys.users.index');
            Route::apiResource('questions', QuestionController::class);
            Route::post('questions/{question}/archive', [QuestionController::class, 'archive'])->name('questions.archive');
            Route::post('questions/{question}/unarchive', [QuestionController::class, 'unarchive'])->name('questions.unarchive');
            Route::apiResource('questionnaires', QuestionnaireController::class);
            Route::post('questionnaires/draft', [QuestionnaireController::class, 'draft'])->name('questionnaires.draft');
            Route::post('questionnaires/{questionnaire}/archive', [QuestionnaireController::class, 'archive'])->name('questionnaires.archive');
            Route::post('questionnaires/{questionnaire}/unarchive', [QuestionnaireController::class, 'unarchive'])->name('questionnaires.unarchive');
            Route::get('questionnaires/{questionnaire}/questions', [QuestionnaireQuestionController::class, 'index'])->name('questionnaires.questions.index');
            Route::post('questionnaires/{questionnaire}/questions/link', [QuestionnaireQuestionController::class, 'link'])->name('questionnaires.questions.link');
            Route::post('questionnaires/{questionnaire}/questions/unlink', [QuestionnaireQuestionController::class, 'unlink'])->name('questionnaires.questions.unlink');
            Route::post('questionnaires/{questionnaire}/questions/reorder', [QuestionnaireQuestionController::class, 'reorder'])->name('questionnaires.questions.reorder');
            Route::apiResource('themes', ThemeController::class);
            Route::get('themes/{theme}/questions', [ThemeQuestionController::class, 'index'])->name('themes.questions.index');
            Route::post('themes/{theme}/questions/attach', [ThemeQuestionController::class, 'attach'])->name('themes.questions.attach');
            Route::post('themes/{theme}/questions/detach', [ThemeQuestionController::class, 'detach'])->name('themes.questions.detach');
            Route::post('themes/{theme}/questions/sync', [ThemeQuestionController::class, 'sync'])->name('themes.questions.sync');
        });
    Route::prefix('me')->middleware(['api', 'auth.cognito', 'check.active.financer', 'check.permission', 'check.allowed.financer'])
        ->name('me.')->group(function (): void {
            Route::prefix('survey')->name('survey.')->group(function (): void {
                Route::get('surveys', [MeSurveyController::class, 'index'])->name('surveys.index');
                Route::get('surveys/{survey}', [MeSurveyController::class, 'show'])->name('surveys.show');
                Route::apiResource('answers', MeAnswerController::class);
                Route::apiResource('submissions', MeSubmissionController::class);
                Route::post('submissions/{submission}/complete', [MeSubmissionController::class, 'complete'])->name('submissions.complete');
                Route::get('themes', [MeThemeController::class, 'index'])->name('themes.index');
                Route::put('surveys/{survey}/toggle-favorite', [MeSurveyController::class, 'toggleFavorite'])->name('surveys.toggle-favorite');
            });
        });
});
