<?php

/*

Author:  Chris Flynn
Date: 	 2/11/16
Project: Media Service

The Problem

PHP application that uses the data from the fictional Media API and the MySQL tables to produce a list of stories in JSON format, along with the additional information consumed from other sources. The resulting data structure should contain one record for each of the four stories in the database. The output for each story should look like the following:

Example: Output JSON

{
    "title": "STORY_TITLE",
    "author": "AUTHOR_FULLNAME",
    "published": "DATE_PUBLISHED",
    "media": [
        { "image": "URL", "credit": "CREDIT", "caption": "CAPTION" },
        { "audio": "URL", "duration": "DURATION" }
    ]
}
Notes

DATE_PUBLISHED should be in the following format: Thursday, October 15, 2015 at 6:15 PM
DURATION should be in “minutes:seconds” format: 03:22
STORY_TITLE should be the title of the story in “Title Case”
AUTHOR_FULLNAME should be the name of the author in “Title Case”

*/

/**
 *
 * Return media JSON object received from an external URL as an associative array.
 *
 */
function get_media_json(){

	try{

        // Build JSON call
        $ch = curl_init('http://example.api.npr.org/stories/media');

        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $json_returned = curl_exec($ch); // JSON returned from call

        curl_close($ch);
        
        return json_decode($json_returned, true);

    }catch(Exception $e){
        throw($e);
    }
}

/**
 *
 * Return all records from a given database.
 *
 */
function get_object_from_db($table){

	$servername = 'localhost';
	$username 	= 'root';
	$password 	= '';
	$dbname 	= 'npr';

	// Create connection
	$conn = new mysqli($servername, $username, $password, $dbname);

	// Check connection
	if ($conn->connect_error) {
	    die('Connection failed: ' . $conn->connect_error);
	} 

	$sql 	 = 'SELECT * FROM ' . $table;
	$results = $conn->query($sql);

	$conn->close();

	return $results;

}

/**
 *
 * Gather JSON objects and database objects to build the array 
 * that will become the JSON object to be returned by this service.
 *
 */
function get_story_list(){
	// Get media JSON
	$medias = get_media_json();

	// Create MySQL objects
	$stations = get_object_from_db('stations');
	$authors  = get_object_from_db('authors');
	$stories  = get_object_from_db('stories');

	$story_list = array();

	// Build from the stories object
	foreach($stories as $story){

		$story_item = [];

		// Add story title
		$story_item['title'] = ucwords($story['title']);
		
		// Add author
		foreach($authors as $author){
			if($story['authorId'] == $author['id']){
				$story_item['author'] = ucwords($author['fullName']); 
			}
		}

		// Add formatted date published
		$date = new DateTime($story['datePublished']);
		$story_item['published'] = $date->format('l\, F j\, Y \a\t h:i A');

		// Media assets
		$media_arr = [];
		foreach ($medias as $media) {
			if($media['story'] == $story['storyid']){
				// Format the duration, if it exists
				if(isset($media['duration'])){
					$timestamp = '';
					$hours 	  = floor($media['duration'] / 3600);
					$minutes  = floor(($media['duration'] - ($hours*3600)) / 60);
					$seconds  = floor($media['duration'] % 60);

					if($hours > 0){
						$timestamp .= (($hours < 10)? '0' : '') . $hours . ':';
					}
					$timestamp .= (($minutes < 10)? '0' : '') . $minutes . ':';
					$timestamp .= (($seconds < 10)? '0' : '') . $seconds;
					$media['duration'] = $timestamp; 
				}
				unset($media['story']);
				// Add media asset to array
				array_push($media_arr, $media);
			}
		}
		// Add all media, only if they exist
		if(!empty($media_arr)){
			$story_item['media'] = $media_arr;
		}

		array_push($story_list, $story_item);
	}

	return $story_list;
}

date_default_timezone_set('UTC'); // Set to handle time formatting

// Available actions of the API
$actions = array('story_list');
// Init value to be returned, if there's an issue, return this error message
$value   = 'An error has occurred';

// Catches the GET statement
if (isset($_GET['action']) && in_array($_GET['action'], $actions)){

	switch ($_GET['action']){
		case 'story_list':
			$value = get_story_list();
			break;

		// Other GET statements if available... 
	}

}

header('Content-Type: application/json'); // Formats return as JSON
echo json_encode($value); // Return the JSON object

/*

Object returns the follow JSON with the required parameters supplied before...

[
	{
      "title": "At Boston Forum, Federal And Local Officials Discuss Region’s Transportation Future",
      "author": "Zeninjor Enwemeka",
      "published": "Wednesday, October 14, 2015 at 12:22 PM",
      "media": [
		{
            "type": "mp4",
            "duration": "04:14",
            "href": "http://foo.bar.mp4"
        },
		{
            "type": "jpg",
            "caption": "The Boston area ranks sixth for gridlock-plagued commutes in 2014. Here's morning traffic on Route 1 into Boston in February.",
            "credit": "Jesse Costa/WBUR",
            "href": "http://s3.amazonaws.com/media.wbur.org/wordpress/1/files/2015/08/0213_am-traffic02.jpg"
         }
      ]
   },
   {
      "title": "Boston’s Transportation Future? City Releases Report Detailing Public’s Transit Goals",
      "author": "Zeninjor Enwemeka",
      "published": "Friday, October 9, 2015 at 09:17 AM"
   },
   {
      "title": "Sen. Murphy Seeks Feedback From “Fed Up” Car Commuters",
      "author": "Ryan Caron King",
      "published": "Thursday, October 15, 2015 at 04:25 PM"
   },
   {
      "title": "Solar Installations Skyrocket, But Connecticut Consumers Still Need to Do Their Homework",
      "author": "Patrick Skahill",
      "published": "Thursday, October 15, 2015 at 09:12 AM",
      "media": [
	      {
	         "type": "jpg",
	         "caption": "Some news story caption",
	         "credit": "AP",
	         "href": "http://foo.bar.jpg"
	      }
      ]
   }
]

*/
