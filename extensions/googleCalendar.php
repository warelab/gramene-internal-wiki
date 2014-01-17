<?php
# Google Calendars
#
# Tag :
#   <googlecalendar>docid</googlecalendar>
# Ex :
#   from url http://calendar.google.com/calendarplay?docid=6444586097901795775
#   <googlecalendar>6444586097901795775</googlecalendar>
#
# Enjoy !

$wgExtensionFunctions[] = 'wfGoogleCalendar';
$wgExtensionCredits['parserhook'][] = array(
        'name' => 'Google Calendar',
        'description' => 'Display Google Calendar',
        'author' => 'Kasper Souren',
        'url' => '/wiki/Google_Calendar_MediaWiki_plugin'
);

function wfGoogleCalendar() {
        global $wgParser;
        $wgParser->setHook('googlecalendar', 'renderGoogleCalendar');
}

# The callback function for converting the input text to HTML output
function renderGoogleCalendar($input) {
        $input = htmlspecialchars($input);
        $width = 425;
        $height = 350;

        $output = '<iframe src="http://www.google.com/calendar/embed?src='.$input.'&chrome=NAVIGATION&height=600&epr=4" style=" border-width:0 " width="480" frameborder="0" height="600"></iframe>';

        return $output;
}
?>
