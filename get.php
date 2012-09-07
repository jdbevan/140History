<?php

$username = "jdbevan";

// Define limits: https://dev.twitter.com/docs/api/1/get/statuses/user_timeline
define("TWEET_TOTAL", 3200);
$count = 200;
$tweets_downloaded = 0;

$twitter = "https://twitter.com/statuses/user_timeline/$username.rss?count=$count";
$max_id = "";
$all_tweets = array();
$all_hashtags = array();
$download_more = true;

while ($download_more and $tweets_downloaded < TWEET_TOTAL) {
	$rss = simplexml_load_file($twitter . $max_id);
	if ($rss !== false) {
		foreach($rss->channel->item as $tweet) {
			// SimpleXML namespacing
			$twitter_namespace = $tweet->children("http://api.twitter.com");
			// Extract hashtags
			$contains_hashtags = preg_match_all("/(^|[^a-zA-Z0-9_])#([a-zA-Z][a-zA-Z0-9_]*)/",
												(string)$tweet->title,
												$hashtags);
			// Store content
			$all_tweets[] = array(
				"content" => str_replace($username . ": ", "", (string)$tweet->title),
				"pubDate" => (string)$tweet->pubDate,
				"id" => preg_replace("/[^0-9]/", "", (string)$tweet->guid),
				"link" => (string)$tweet->link,
				"source" => (string)$twitter_namespace->source,
				"hashtags" => (count($hashtags)>1 and count($hashtags[2])>0) ? $hashtags[2] : array()
			);
			// Store hashtags
			if (count($hashtags)>1 and count($hashtags[2])>0) {
				foreach($hashtags[2] as $tag) {
					if (isset($all_hashtags[$tag])) {
						$all_hashtags[$tag]++;
					} else {
						$all_hashtags[$tag] = 1;
					}
				}
			}
		}
		// Obtain previous (older) ID
		$oldestID = $all_tweets[count($all_tweets) - 1]['id'];
		$max_id = "&max_id=" . bcsub($oldestID, '1');
	} else {
		echo "Oops, you broke it.\n";
	}
	$latest_count = count($all_tweets);
	if ($latest_count - $tweets_downloaded == $count) {
		$tweets_downloaded += $count;
		// Just in case Twitter hate us all...
		sleep(5);
	} else {
		$tweets_downloaded = $latest_count;
		$download_more = false;
	}
}
echo "#\n# Here are all your hashtags in order\n#\n";
if (count($all_hashtags)>0) {
	arsort($all_hashtags);
	print_r($all_hashtags);
}
echo "\n\n";
echo "#\n# Here are all your tweets in order\n#\n";
foreach($all_tweets as $tweet) {
	echo $tweet['id'], "\t", $tweet['pubDate'], "\t", $tweet['content'], "\t", $tweet['link'], "\t", $tweet['source'], "\n";
}
?>
