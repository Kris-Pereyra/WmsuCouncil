<?php
declare(strict_types=1);
/** Public lists used by the sign-up form (colleges, programs per college, school years). */

out([
    'colleges'    => colleges_list(),
    'programs'    => (object)programs_by_college(),
    'schoolYears' => school_year_options(),
    'suffixes'    => ['Jr.', 'Sr.', 'II', 'III', 'IV', 'V', 'Other'],
]);
