<?php

return [
    // For now Higher Life serves text affirmations only. Video and audio files stay in the database untouched
    // but are hidden everywhere (API, admin, collections, favourites). Set CONTENT_TEXT_ONLY=false to bring them back.
    'text_only' => (bool) env('CONTENT_TEXT_ONLY', true),

    // Every new account gets this many days of full access before it has to subscribe. 0 turns the trial off.
    'trial_days' => (int) env('TRIAL_DAYS', 30),
];
