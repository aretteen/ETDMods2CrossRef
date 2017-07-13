<?php
/*
DISSERTATION CROSSREF METADATA - for body of crossref metadata XML

<person_name contributor_role="author" sequence="first">
	<given_name>$var</given_name>
	<surname>$var</surname>
	<affiliation>Florida State University</affiliation>
</person_name>

<titles>
	<title>$var</title>
	<subtitle></subtitle>
</titles>

<approval_date media_type="online">
	<month>$var_MM</month>
	<day>$var_DD</day>
	<year>$var_YYYY</year>
</approval_date>

<institution>
	<institution_name>Florida State University</institution_name>
	<institution_acronym>FSU</institution_acronym>
</institution>

<degree>
	$var_degree -> stored in $xml->extension->etd:degree->etd:name
</degree>

<publisher_item>
	<item_number item_number_type="IID">$var_IID</item_number>
</publisher_item>

<doi_data>
	<doi>$var_DOI</doi>
	<timestamp>$var_timestamp</timestamp>
	<resource>$var_PURL</resource>
</doi_data>

// UPDATE SCRIPT TO GRAB DOI data from <identifier type="doi"></identifier>
*/
// $modsFile = "Zeoli_fsu_0071E_13456.xml";

$target_file_mods = basename($_FILES['MODS']['name']);
$modsFile = $_FILES['MODS']['tmp_name'];
$modsFileType = pathinfo($target_file_mods,PATHINFO_EXTENSION);



// Verifies the uploaded file is XML
if($modsFileType != "xml" ) {
    echo "Sorry, only XML files are allowed.";
} else {


$modsXML = simplexml_load_file($modsFile) or die ("Problem with loading XML");

//
// To Capture Date of Defense (for "approval_date")
//
foreach ($modsXML->note as $note){
	if ($note['displayLabel'] == "Date of Defense"){
	$approvalDate = $note; // Need to take this textual string and convert to date; November 18, 2016 -> 2016-11-18
	}
}
	//
	// Parse Date String into W3C compliant format
	//
	$unixDate = strtotime($approvalDate);
	$w3cDateApproval_month = date('m', $unixDate);
	$w3cDateApproval_day = date('d', $unixDate);
	$w3cDateApproval_year = date('Y', $unixDate);

//
// PARSE AND PROCESS AUTHOR 
//

foreach ($modsXML->name as $name){
	
	if ($name->role->roleTerm == "author"){
		
		foreach ($name->namePart as $namePart){
			if ($namePart['type'] == 'given') { $authorNameGiven = $namePart; }
			if ($namePart['type'] == 'family') { $authorNameFamily = $namePart; }
		}
	}
}

// STORING VARIABLES FOR TRANSFORMATION

$title = $modsXML->titleInfo->title;
//
// APPEND NONSORT TITLE, if exist
//
$nonSort = $modsXML->titleInfo->nonSort;
if ($nonSort) { $title = $nonSort . " {$modsXML->titleInfo->title}"; }


$degree = $modsXML->extension->children('etd', true)->degree->name;
$subtitle = $modsXML->titleInfo->subTitle;
$institution_name = "Florida State University";
$institution_acronym = "FSU";
$institution_place = "Tallahassee";
$timestamp = time();

// Parse through the multiple Identifier elements to pull out IID and DOI

foreach ($modsXML->identifier as $identifier){
	if($identifier['type'] == "IID"){
		$IID = $identifier;
	}
	
	if($identifier['type'] == "doi"){
		$doi = $identifier;
	}
}

$resource = "http://purl.flvc.org/fsu/fd/" . $IID;

// 

//
// CROSSREF XML GENERATION
//

$depositor_name = "Florida State University";
$depositor_contact = "aretteen@fsu.edu"; // Change contact depending who is doing the CrossRef registration

$xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><doi_batch xmlns="http://www.crossref.org/schema/4.4.0" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" version="4.4.0" xsi:schemaLocation="http://www.crossref.org/schema/4.4.0 http://www.crossref.org/schemas/crossref4.4.0.xsd"></doi_batch>');

$xml->addChild('head');

$xml->head->addChild('doi_batch_id', $timestamp);
$xml->head->addChild('timestamp', $timestamp);
$xml->head->addChild('depositor');
$xml->head->depositor->addChild('depositor_name', $depositor_name);
$xml->head->depositor->addChild('email_address', $depositor_contact);
$xml->head->addChild('registrant', $institution_name);

$xml->addChild('body');

$xml->body->addChild('dissertation');

$xml->body->dissertation->addChild('person_name')->addAttribute('contributor_role', 'author');
$xml->body->dissertation->person_name->addAttribute('sequence', 'first');
$xml->body->dissertation->person_name->addChild('given_name', $authorNameGiven);
$xml->body->dissertation->person_name->addChild('surname', $authorNameFamily);
$xml->body->dissertation->person_name->addChild('affiliation', $institution_name);

$xml->body->dissertation->addChild('titles');
$xml->body->dissertation->titles->addChild('title', $title);
if ($subtitle){
$xml->body->dissertation->titles->addChild('subtitle', $subtitle);
}

$xml->body->dissertation->addChild('approval_date')->addAttribute('media_type', 'online');
$xml->body->dissertation->approval_date->addChild('month', $w3cDateApproval_month);
$xml->body->dissertation->approval_date->addChild('day', $w3cDateApproval_day);
$xml->body->dissertation->approval_date->addChild('year', $w3cDateApproval_year);

$xml->body->dissertation->addChild('institution');
$xml->body->dissertation->institution->addChild('institution_name', $institution_name);
$xml->body->dissertation->institution->addChild('institution_acronym', $institution_acronym);
$xml->body->dissertation->institution->addChild('institution_place', $institution_place);

$xml->body->dissertation->addChild('degree', $degree);

// Have to add a string length check here, since they will reject identifiers > 32 characters
if (strlen($IID) <= 32) {
$xml->body->dissertation->addChild('publisher_item');
$xml->body->dissertation->publisher_item->addChild('item_number', $IID)->addAttribute('item_number_type', 'IID');
}
$xml->body->dissertation->addChild('doi_data');
$xml->body->dissertation->doi_data->addChild('doi', $doi);
$xml->body->dissertation->doi_data->addChild('timestamp', $timestamp);
$xml->body->dissertation->doi_data->addChild('resource', $resource);

//
// WRITE XML FILE TO DRIVE
// I believe CrossRef supports API feeding the CrossRef XML, will look into it for v2
//
$handle = __DIR__ . "/output/{$IID}_crossref.xml";
$output = fopen($handle,"w");
$dom = new DOMDocument('1.0');
$dom->preserveWhiteSpace = false;
$dom->formatOutput = true;
$dom->loadXML($xml->asXML());
$write = fwrite($output,$dom->saveXML());
fclose($output);

if ($write) {
 print "Successfully generated CrossRef XML for content-type Dissertation.<br><br>File saved to: {$handle}";
}
else {
 print "File did not write.";
}
}
?>