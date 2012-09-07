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

function tableOfHashtags($tags) {
	$total = count($tags);
	arsort($tags);
	$html = "<table class='hashtags'>
	<thead>
		<tr><th>Hashtag</th><th>Tweets</th><th>%</th></tr>
	</thead>
	<tbody>";
	foreach ($tags as $tag => $num) {
		if ($num < 3) break;
		$html .= "<tr><td><a href='https://twitter.com/#!/search/realtime/%23$tag'>$tag</a></td><td>$num</td><td>" . number_format($num/$total*100,2) . "</td></tr>\n";
	}
	$html .= "</tbody></table>\n";
	return $html;
}

function graphOfTweetsByDay($tweets) {
	$days = array();
	$prev_date = null;
	foreach($tweets as $tweet) {
		$date = date("Y-m-d", strtotime($tweet['pubDate']));
		if ($prev_date != $date) {
			if ($prev_date !== null) {
				while ($prev_date != $date) {
					$prev_date = date("Y-m-d", strtotime($prev_date . " -1 day"));
					$days[$prev_date] = 0;
				}
			}
			$prev_date = $date;
		}
		if (!isset($days[$date])) {
			$days[$date] = 1;
		} else {
			$days[$date]++;
		}
	}
	$json = "['Date', 'Tweets'],\n";
	foreach($days as $day => $num) {
		$json .= "['$day', $num],\n";
	}
	$json = "[" . substr($json, 0, -2) . "]\n";

	$html = "<script type=\"text/javascript\">
      google.load(\"visualization\", \"1\", {packages:[\"corechart\"]});
      google.setOnLoadCallback(function() {
        var data = google.visualization.arrayToDataTable($json);

        var options = {
		      title: 'Tweets over Time',
		      hAxis: {title: 'Date',
		      			direction: -1},
		      legend: { position: 'none' }
		    },
        	chart = new google.visualization.ColumnChart(document.getElementById('days_chart_div'));
	    chart.draw(data, options);
      });
    </script>
    <div id=\"days_chart_div\" style=\"width: 550px; height: 250px;\"></div>";

    return $html;
}

function graphOfTweetsByHour($tweets) {
	$hours = array();
	for($h=23;$h>=0;$h--) {
		$hours[sprintf("%02s",$h) . ":00"] = 0;
	}
	foreach($tweets as $tweet) {
		$hour = date("H:00", strtotime($tweet['pubDate']));
		$hours[$hour]++;
	}
	$json = "['Hour', 'Tweets'],\n";
	foreach($hours as $hour => $num) {
		$json .= "['$hour', $num],\n";
	}
	$json = "[" . substr($json, 0, -2) . "]\n";
	
	$html = "<script type=\"text/javascript\">
      google.load(\"visualization\", \"1\", {packages:[\"corechart\"]});
      google.setOnLoadCallback(function() {
        var data = google.visualization.arrayToDataTable($json);

        var options = {
		      title: 'Tweets by Hour',
		      hAxis: {title: 'Hour',
		      			direction: -1},
		      legend: { position: 'none' }
		    },
        	chart = new google.visualization.ColumnChart(document.getElementById('hours_chart_div'));
	    chart.draw(data, options);
      });
    </script>
    <div id=\"hours_chart_div\" style=\"float: left; width: 550px; height: 250px;\"></div>";

    return $html;
}

function footer() {
	return "<p class='footer'>Analysis thanks to <a href='https://github.com/jdbevan/140History'>140History</a> by Jon Bevan</p>\n";
}
function styling() {
	return "body { font-family: Arial; font-size: 10pt; }
.footer { clear: both; }
.hashtags thead tr th { text-align: left; background-color: #E0E0E0; }
.hashtags tbody tr:nth-child(2n) { background-color: #F0F0F0; }
.hashtags th, .hashtags td { padding: 5px; }";
}
function htmlPage($title, $content) {
	echo "<!DOCTYPE html>
<html>
<head>
	<title>$title</title>
	<style>\n";
	echo styling();
	echo "</style>
	<script type=\"text/javascript\" src=\"https://www.google.com/jsapi\"></script>
</head>
<body>\n";
	echo $content;
	echo footer();
	echo "</body>
</html>\n";
}

$content = tableOfHashtags($all_hashtags);
$content .= graphOfTweetsByHour($all_tweets);
$content .= graphOfTweetsByDay($all_tweets);

htmlPage($username . " tweet analysis", $content);
exit;

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
