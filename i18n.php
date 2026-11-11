<?php
// Small UI dictionary for the two languages a session can be set to.
// Only interface chrome -- questions/answers themselves are whatever
// the moderator types and are never translated.

function pollbeamTranslations() {
    return [
        'en' => [
            'loading' => 'Loading session…',
            'liveSession' => 'Live Session',
            'errorPrefix' => 'Error: ',
            'pollBadge' => 'POLL',
            'yourAnswerPlaceholder' => 'Your answer...',
            'submit' => 'Submit',
            'vote' => 'Vote',
            'thanksAnswer' => 'Thanks for your answer!',
            'noAnswersYet' => 'No answers yet',
            'participantLink' => 'Participant link:',
        ],
        'de' => [
            'loading' => 'Session wird geladen…',
            'liveSession' => 'Live-Session',
            'errorPrefix' => 'Fehler: ',
            'pollBadge' => 'UMFRAGE',
            'yourAnswerPlaceholder' => 'Deine Antwort...',
            'submit' => 'Absenden',
            'vote' => 'Wählen',
            'thanksAnswer' => 'Danke für deine Antwort!',
            'noAnswersYet' => 'Noch keine Antworten',
            'participantLink' => 'Teilnehmer-Link:',
        ],
    ];
}

// Looks up a session's language by its code. Falls back to 'en' if the
// session doesn't exist (yet) or the code is empty -- matches the page's
// own "demo" fallback so the very first paint is never in the wrong language.
function pollbeamSessionLanguage($db, $code) {
    if ($code === '') {
        return 'en';
    }
    $stmt = $db->prepare("SELECT language FROM sessions WHERE code = ?");
    $stmt->execute([$code]);
    $row = $stmt->fetch();
    if ($row && in_array($row['language'], ['de', 'en'], true)) {
        return $row['language'];
    }
    return 'en';
}
