<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * StudyMark activity plugin.
 *
 * @package    mod_studymark
 * @copyright  2026 Álvaro Gregori <50322409+alvarogregori@users.noreply.github.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$string['pluginname'] = 'StudyMark';
$string['modulename'] = 'StudyMark';
$string['modulenameplural'] = 'StudyMark activities';
$string['pluginadministration'] = 'StudyMark administration';
$string['studymarkname'] = 'Activity name';
$string['studymarkname_help'] = 'Name of the collaborative reading activity.';
$string['documents'] = 'Documents';
$string['documents_help'] = 'PDF, EPUB, HTML or plain-text documents available in this activity.';
$string['groupmode'] = 'Use Moodle course groups to control shared annotations.';
$string['privacy:metadata:studymark_document'] = 'Stores document metadata and uploader identity.';
$string['privacy:metadata:studymark_highlight'] = 'Stores highlights made by learners.';
$string['privacy:metadata:studymark_comment'] = 'Stores comments made by learners.';
$string['privacy:metadata:userid'] = 'The Moodle user ID.';
$string['privacy:metadata:content'] = 'User-authored annotation content.';
$string['eventactivityviewed'] = 'StudyMark activity viewed';
$string['eventhighlightcreated'] = 'Highlight created';
$string['eventcommentcreated'] = 'Comment created';
$string['errorinvaliddocument'] = 'The requested document is not available in this activity.';


$string['analytics'] = 'Highlighting report';
$string['analyticstitle'] = 'Highlighting report: {$a}';

$string['completiondisabled'] = 'Do not require';
$string['completionpercentage'] = '{$a}%';
$string['completionprogress'] = 'Minimum reading progress';
$string['completionprogress_help'] = 'The activity is complete when the learner’s furthest highlight reaches at least this percentage of the document.';
$string['completionmatch'] = 'Minimum reference match';
$string['completionmatch_help'] = 'The activity is complete when the learner’s highlighting reaches this match with manager reference highlights. A reference must exist.';
$string['completionprogressdesc'] = 'Reach at least {$a}% of the document through highlighting';
$string['completionmatchdesc'] = 'Reach at least {$a}% match with the reference highlighting';

$string['readertools'] = 'Reading tools';
$string['summaryreading'] = 'Summary reading';
$string['fullreading'] = 'Full reading';
$string['originaldocument'] = 'Original document';
$string['exittocourse'] = 'Return to course';
$string['togglefullscreen'] = 'Toggle full screen';
$string['documentoutline'] = 'Document outline';
$string['outline'] = 'Contents';
$string['loadingdocument'] = 'Loading document…';
$string['noheadings'] = 'This document has no headings.';
$string['nosummaryhighlights'] = 'There are no highlights yet for summary reading.';
$string['nodocument'] = 'This activity does not have a document yet.';
$string['documentprocesserror'] = 'The document could not be processed.';
$string['deletehighlightconfirm'] = 'Delete this highlight?';
$string['extractingdocument'] = 'Extracting document content…';
$string['downloaddocumenterror'] = 'The document could not be downloaded.';
$string['pdfjsunavailable'] = 'PDF.js is unavailable.';
$string['extractingpage'] = 'Extracting page';
$string['of'] = 'of';
$string['jszipunavailable'] = 'JSZip is unavailable.';
$string['invalidepub'] = 'The EPUB does not contain a valid OPF package.';
$string['invaliddocx'] = 'The DOCX does not contain document.xml.';
$string['analyzingdocument'] = 'Analysing the document…';
$string['referencecontains'] = 'The reference contains';
$string['highlightedcharacters'] = 'highlighted characters.';
$string['noreferencehelp'] = 'There is no reference highlighting yet. Open the document with a manager account, make sure Reference highlighting mode (✓) is active, and highlight the reference passages.';
$string['readingreached'] = 'Document progress';
$string['detaillevel'] = 'Amount highlighted';
$string['comparedaverage'] = 'on avg';
$string['match'] = 'Match';
$string['noreference'] = 'No reference';
$string['courseaveragedetail'] = 'Course average detail';
$string['documentlength'] = 'Document length';
$string['characters'] = 'characters';
$string['student'] = 'Student';
$string['highlights'] = 'Highlights';
$string['detail'] = 'Amount highlighted';
$string['referencematch'] = 'Reference match';
$string['nostudents'] = 'There are no enrolled students with permission to highlight.';
$string['calculationheading'] = 'How these figures are calculated';
$string['reportguideheading'] = 'How to read this report';
$string['reportguiderelevance'] = 'Relevance: does the student highlight well or highlight too much?';
$string['reportguidecoverage'] = 'Coverage: does the student miss important content?';
$string['reportguidebalance'] = 'Balance: an overall score combining both measures.';
$string['reportguideaverage'] = 'The comparison indicators show the difference from the class average.';
$string['simpleview'] = 'Simple view';
$string['showtechnical'] = 'Show technical columns';
$string['hidetechnical'] = 'Hide technical columns';
$string['generalstatus'] = 'Overall status';
$string['statusgood'] = 'Good balance';
$string['statusdeveloping'] = 'Developing';
$string['statusreview'] = 'Needs review';
$string['statusnodata'] = 'No data';
$string['interpretationhigh'] = 'Good balance: identifies much of the key content without over-highlighting.';
$string['interpretationmedium'] = 'Developing: review the selection and any important content that was missed.';
$string['interpretationlow'] = 'Needs review: the selection differs substantially from the tutor reference.';
$string['interpretationnone'] = 'Not enough data: the student has not highlighted anything yet.';
$string['progresscalculation'] = 'how far the student has reached while highlighting.';
$string['detailcalculation'] = 'the percentage of the document text highlighted by the student.';
$string['precision'] = 'Relevance';
$string['recall'] = 'Coverage of key content';
$string['f1score'] = 'Balance';
$string['jaccard'] = 'Overall match';
$string['precisioncalculation'] = 'of what the student highlighted, how much matches what the tutor considered important.';
$string['recallcalculation'] = 'how much of the tutor-highlighted content the student captured.';
$string['f1calculation'] = 'an overall score balancing relevance and coverage using their harmonic mean.';
$string['matched'] = 'Matched';
$string['extra'] = 'Extra';
$string['missing'] = 'Missing';
$string['statuslegend'] = 'Status bands: reading progress is green from 75% and amber from 40%; the remaining percentages are green from 50% and amber from 25%. Colours are indicative and do not replace the exact values.';
$string['matchbreakdown'] = 'Matched, extra and missing highlighted characters';
$string['matchcalculation'] = 'the overall similarity between the student and tutor highlights.';
$string['progressestimate'] = 'Reading progress is an estimate based on highlighting, not confirmation that the content was read.';
$string['reporterror'] = 'The report could not be generated.';
$string['pdfnavigation'] = 'PDF navigation';
$string['previous'] = 'Previous';
$string['next'] = 'Next';
$string['zoomout'] = 'Zoom out';
$string['zoomin'] = 'Zoom in';
$string['loadingpage'] = 'Loading page…';
$string['page'] = 'Page';
$string['hideoutline'] = 'Hide document outline';
$string['showoutline'] = 'Show document outline';
$string['containshighlights'] = 'This section contains highlights';

$string['showreference'] = 'Show tutor reference to learners';
$string['hidereference'] = 'Hide tutor reference from learners';
$string['viewreference'] = 'Show tutor highlighting';
$string['hidereferenceview'] = 'Hide tutor highlighting';
$string['referencemode'] = 'Use personal highlighting';
$string['referencemodeactive'] = 'Reference highlighting mode is active; new highlights form part of the reference';
$string['highlightcolor'] = 'Highlight colour';
$string['selectedcolor'] = 'Selected colour';
$string['yellow'] = 'Yellow';
$string['blue'] = 'Blue';
$string['green'] = 'Green';
$string['pink'] = 'Pink';
$string['orange'] = 'Orange';
$string['deletehighlight'] = 'Delete selected highlight';
