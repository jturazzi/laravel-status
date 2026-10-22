<?php

// Only the rules used by the application forms; anything else falls back to English.
return [
    'boolean' => 'Le champ :attribute doit être vrai ou faux.',
    'max' => [
        'string' => 'Le champ :attribute ne doit pas dépasser :max caractères.',
    ],
    'required' => 'Le champ :attribute est obligatoire.',
    'string' => 'Le champ :attribute doit être une chaîne de caractères.',
    'url' => 'Le champ :attribute doit être une adresse valide.',

    'attributes' => [
        'name' => 'nom',
        'url' => 'adresse',
        'token' => 'jeton',
    ],
];
