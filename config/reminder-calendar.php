<?php

return [
    // Hungarian public holidays and workday changes valid on 2026-11-24.
    // https://njt.jog.gov.hu/jogszabaly/2025-10-20-2X
    // https://njt.jog.gov.hu/jogszabaly/2026-4-20-85
    // https://njt.jog.gov.hu/jogszabaly/2026-72-00-00
    2026 => [
        'days_off' => [
            '01-01', '01-02', '03-15', '04-03', '04-06', '05-01', '05-25',
            '08-20', '08-21', '10-23', '11-01', '12-24', '12-25', '12-26',
        ],
        'workdays' => ['01-10', '08-08'],
    ],
];
