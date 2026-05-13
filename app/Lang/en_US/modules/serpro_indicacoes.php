<?php

return [
    'instructions' => [
        'button' => 'Instructions',
        'title' => 'How Driver Nomination Works',
        'what_is_title' => 'What is Driver Nomination?',
        'what_is_text' => 'When a fleet vehicle receives a traffic fine, the citation is issued to the company (owner). Driver nomination is the legal process of transferring the responsibility of the infraction to the person who was actually driving at the time.',

        'real_infrator_title' => 'Type 1: Actual Offender',
        'real_infrator_desc' => 'Used to transfer the penalty points and responsibility of a specific fine to the person who actually committed the infraction.',
        'real_infrator_when' => 'When receiving a fine and identifying who was driving',
        'real_infrator_prereq' => 'The fine must have been imported via online lookup (with agency code, AIT and infraction code)',
        'real_infrator_fields' => 'Select the fine + driver CPF',
        'real_infrator_result' => 'The infraction points are transferred to the nominated driver\'s license',

        'principal_title' => 'Type 2: Primary Driver',
        'principal_desc' => 'Used to register the regular driver of a vehicle. Not linked to a specific fine.',
        'principal_when' => 'When renting a vehicle to a client, register them as the primary driver',
        'principal_advantage' => 'Future fines for this vehicle will be automatically directed to the registered driver',
        'principal_fields' => 'Vehicle plate + CPF + driver\'s license number',
        'principal_important' => 'Can be removed when the rental period ends',

        'status_title' => 'Nomination Status',
        'status_enviado' => 'Nomination sent to the online lookup system',
        'status_processando' => 'Under review by the agency',
        'status_aceito' => 'Nomination accepted successfully',
        'status_rejeitado' => 'Nomination rejected by the agency',
        'status_cancelado' => 'Cancelled by the rental company',
        'status_expirado' => 'Nomination deadline expired',

        'important_title' => 'Important Information',
        'important_1' => 'Actual Offender nomination only works for fines imported via online lookup (not for manually registered fines)',
        'important_2' => 'The company CNPJ must be configured in online lookup settings before making nominations',
        'important_3' => 'After submission, use the sync button to check the updated status in the online lookup system',
        'important_4' => 'Nominations with "Sent" or "Processing" status can be cancelled',
        'important_5' => 'Respect legal deadlines: the nomination must be made within the period specified in the notification',

        'steps_title' => 'Step by Step',
        'step_1' => 'Click on "New Nomination"',
        'step_2' => 'Choose the type: Actual Offender or Primary Driver',
        'step_3' => 'For Actual Offender: select the fine and enter the driver\'s CPF',
        'step_4' => 'For Primary Driver: enter the plate, CPF and driver\'s license number',
        'step_5' => 'Click on "Submit Nomination"',
        'step_6' => 'Track the status in the table and use the sync button to update',

        'label_when' => 'When to use:',
        'label_prereq' => 'Prerequisite:',
        'label_fields' => 'Required fields:',
        'label_result' => 'Result:',
        'label_advantage' => 'Advantage:',
        'label_important' => 'Important:',
    ],
];
