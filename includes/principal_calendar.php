<?php
function buildWeeksForMonth(string $monthStart, string $monthEndExclusive): array {
    $firstDay = new DateTime($monthStart);
    $lastDay  = (new DateTime($monthEndExclusive))->modify('-1 day');

    $weekCursor = clone $firstDay;
    $weekCursor->modify('monday this week');

    $weeksToShow = [];
    while ($weekCursor <= $lastDay) {
        $isoYear = (int)$weekCursor->format('o');
        $isoWeek = (int)$weekCursor->format('W');
        $yw = (int)($isoYear . str_pad((string)$isoWeek, 2, '0', STR_PAD_LEFT));
        $weeksToShow[] = $yw;
        $weekCursor->modify('+7 days');
    }

    $weekLabels = [];
    foreach ($weeksToShow as $idx => $yw) {
        $weekLabels[$yw] = 'Semana ' . ($idx + 1);
    }

    return [$weeksToShow, $weekLabels];
}
