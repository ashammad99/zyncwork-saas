<?php

// Public site text (Landing: home, pricing, legal pages, contact, and the footer on the auth pages).
// English only for now; a translation goes in lang/<locale>/site.php.

return [

    'nav' => [
        'home' => 'Home',
        'pricing' => 'Pricing',
        'contact' => 'Contact',
        'sign_in' => 'Sign in',
        'dashboard' => 'Dashboard',
        'start_trial' => 'Start free trial',
    ],

    'footer' => [
        'heading' => 'Legal and contact',
        'terms' => 'Terms of Service',
        'privacy' => 'Privacy Policy',
        'refunds' => 'Refund Policy',
        'copyright' => '© :year :operator. All rights reserved.',
    ],

    'operated_by' => 'Operated by :name',

    'home' => [
        'title' => 'Project management and time tracking for teams',
        'description' => 'Zyncwork brings projects, tasks, time tracking and approvals, and time reports together in one workspace for your company, with separate views for admins, managers and employees.',
        'hero' => [
            'heading' => 'Plan the work, track the time, approve it in one place',
            'body' => 'Zyncwork is a project-management workspace for companies. Managers plan projects and tasks for their department, employees track the time they spend, and managers review and approve it, so the hours you report are hours someone checked.',
            'trial' => '{1} Try it free for :days day, no card required.|[2,*] Try it free for :days days, no card required.',
        ],
        'features_heading' => 'What Zyncwork does',
        'features' => [
            'portals' => [
                'title' => 'A view for every role',
                'body' => 'Admins, managers and employees each get their own portal. Admins manage the company, managers run their department\'s projects, and employees see the tasks assigned to them.',
            ],
            'projects' => [
                'title' => 'Projects by department',
                'body' => 'Group work into projects that belong to a department. Each project shows its tasks, progress and a timeline of what happened.',
            ],
            'tasks' => [
                'title' => 'Tasks with a clear workflow',
                'body' => 'Tasks move from backlog to to-do, in progress, in review and completed. Set priority, impact and effort, and make a task wait for another one to finish first. Managers review finished work before it counts as done.',
            ],
            'time' => [
                'title' => 'Time tracking and approval',
                'body' => 'Employees start a timer on a task or add time by hand. Managers approve the time logged in their department, and approved entries are locked.',
            ],
            'collaboration' => [
                'title' => 'Comments and mentions',
                'body' => 'Discuss a task where the work is. Mention a colleague to bring them in, and they get an in-app notification.',
            ],
            'reports' => [
                'title' => 'Dashboards and time reports',
                'body' => 'Dashboards show where work stands. Export time reports as CSV or PDF for payroll, invoicing or a client.',
            ],
            'company' => [
                'title' => 'Your company, your users',
                'body' => 'Invite colleagues by email and choose their role. Deactivate someone who leaves, and see who changed what in the activity log.',
            ],
            'data' => [
                'title' => 'Your data stays yours',
                'body' => 'Every company\'s data is kept separate from every other company\'s. An admin can download a full export of the company\'s data at any time.',
            ],
        ],
        'cta' => [
            'heading' => 'Ready to try it with your team?',
            'body' => 'Create your company account, invite your team, and see pricing whenever you\'re ready.',
            'pricing' => 'See pricing',
        ],
    ],

    'pricing' => [
        'title' => 'Pricing',
        'description' => 'Simple per-seat pricing for Zyncwork, with a free trial and no card required.',
        'heading' => 'Simple per-seat pricing',
        'intro' => 'You pay for the seats your company uses. A seat is one active user or one pending invitation.',
        'trial' => [
            'name' => 'Free trial',
            'length' => '{1} :days day|[2,*] :days days',
            'seats' => '{1} Up to :seats user|[2,*] Up to :seats users',
            'no_card' => 'No card required',
            'after' => 'When the trial ends, everyone keeps working, but you can\'t add new users until you choose a plan.',
        ],
        // :amount is in major units with two decimals (e.g. 10.00).
        'price' => ':amount :currency',
        'per_seat' => [
            'month' => 'per seat, per month',
            'year' => 'per seat, per year',
        ],
        // Who processes payments (Paddle as merchant of record) and how tax is shown. Text supplied by the operator.
        'billing_note' => '[PLACEHOLDER: merchant-of-record and tax line]',
        'refunds_link' => 'See our refund policy.',
    ],

    'legal' => [
        'terms' => [
            'title' => 'Terms of Service',
            'description' => 'The terms that apply to using Zyncwork.',
        ],
        'privacy' => [
            'title' => 'Privacy Policy',
            'description' => 'How Zyncwork collects, uses and protects personal data.',
        ],
        'refunds' => [
            'title' => 'Refund Policy',
            'description' => 'When and how Zyncwork payments are refunded.',
        ],
        'placeholder' => 'This document has not been published yet.',
    ],

    'contact' => [
        'title' => 'Contact',
        'description' => 'How to reach the team behind Zyncwork.',
        'heading' => 'Contact us',
        'body' => 'Questions about Zyncwork, your account or billing? Email us and we\'ll get back to you.',
        'email' => 'Email',
        'address' => 'Address',
        'company' => 'Company',
    ],

    // On the signup page, under the form. :terms and :privacy are links.
    'signup_agreement' => 'By creating an account, you agree to the :terms and the :privacy.',

];
