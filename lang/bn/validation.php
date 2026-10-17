<?php

/*
| Bangla validation messages. Rules not listed here fall back to English.
*/

return [
    'accepted' => ':attribute গ্রহণ করতে হবে।',
    'after_or_equal' => ':attribute অবশ্যই :date বা তার পরের তারিখ হতে হবে।',
    'array' => ':attribute অবশ্যই একটি তালিকা হতে হবে।',
    'before_or_equal' => ':attribute অবশ্যই :date বা তার আগের তারিখ হতে হবে।',
    'between' => [
        'numeric' => ':attribute অবশ্যই :min থেকে :max এর মধ্যে হতে হবে।',
        'string' => ':attribute অবশ্যই :min থেকে :max অক্ষরের হতে হবে।',
        'array' => ':attribute-এ :min থেকে :max টি আইটেম থাকতে হবে।',
    ],
    'boolean' => ':attribute অবশ্যই হ্যাঁ অথবা না হতে হবে।',
    'confirmed' => ':attribute দুইবার একই লিখতে হবে।',
    'current_password' => 'বর্তমান পাসওয়ার্ড ভুল।',
    'date' => ':attribute একটি সঠিক তারিখ নয়।',
    'date_format' => ':attribute অবশ্যই :format আকারে হতে হবে।',
    'decimal' => ':attribute-এ দশমিকের পরে :decimal ঘর থাকতে হবে।',
    'different' => ':attribute এবং :other আলাদা হতে হবে।',
    'digits' => ':attribute অবশ্যই :digits অঙ্কের হতে হবে।',
    'email' => ':attribute একটি সঠিক ই-মেইল ঠিকানা হতে হবে।',
    'exists' => 'বেছে নেওয়া :attribute সঠিক নয়।',
    'in' => 'বেছে নেওয়া :attribute সঠিক নয়।',
    'integer' => ':attribute অবশ্যই একটি পূর্ণসংখ্যা হতে হবে।',
    'max' => [
        'numeric' => ':attribute :max এর বেশি হতে পারবে না।',
        'string' => ':attribute :max অক্ষরের বেশি হতে পারবে না।',
        'array' => ':attribute-এ :max টির বেশি আইটেম থাকতে পারবে না।',
    ],
    'min' => [
        'numeric' => ':attribute কমপক্ষে :min হতে হবে।',
        'string' => ':attribute কমপক্ষে :min অক্ষরের হতে হবে।',
        'array' => ':attribute-এ কমপক্ষে :min টি আইটেম থাকতে হবে।',
    ],
    'numeric' => ':attribute অবশ্যই একটি সংখ্যা হতে হবে।',
    'password' => [
        'letters' => ':attribute-এ কমপক্ষে একটি অক্ষর থাকতে হবে।',
        'mixed' => ':attribute-এ কমপক্ষে একটি বড় ও একটি ছোট হাতের অক্ষর থাকতে হবে।',
        'numbers' => ':attribute-এ কমপক্ষে একটি সংখ্যা থাকতে হবে।',
        'symbols' => ':attribute-এ কমপক্ষে একটি চিহ্ন থাকতে হবে।',
        'uncompromised' => 'এই :attribute ফাঁস হওয়া তালিকায় পাওয়া গেছে। অন্য একটি বেছে নিন।',
    ],
    'regex' => ':attribute-এর গঠন সঠিক নয়।',
    'required' => ':attribute দিতে হবে।',
    'size' => [
        'numeric' => ':attribute অবশ্যই :size হতে হবে।',
        'string' => ':attribute অবশ্যই :size অক্ষরের হতে হবে।',
    ],
    'string' => ':attribute অবশ্যই লেখা হতে হবে।',
    'unique' => 'এই :attribute আগেই ব্যবহার হয়েছে।',

    'attributes' => [
        'name' => 'নাম',
        'username' => 'ইউজার নেম',
        'email' => 'ই-মেইল',
        'phone' => 'মোবাইল নম্বর',
        'password' => 'পাসওয়ার্ড',
        'current_password' => 'বর্তমান পাসওয়ার্ড',
        'role' => 'ভূমিকা',
        'locale' => 'ভাষা',
        'login' => 'ইউজার নেম বা ই-মেইল',
        'code' => 'কোড',
        'recovery_code' => 'রিকভারি কোড',
        'value' => 'মান',
        'effective_from' => 'কার্যকর তারিখ',
        'from' => 'শুরুর তারিখ',
        'to' => 'শেষ তারিখ',
    ],
];
