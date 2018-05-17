<?php
add_filter( 'wpdc_comment_body', 'wpdc_custom_comment_body' );
function wpdc_custom_comment_body( $content ) {
	// Allows parsing misformed html. Save the previous value of libxml_use_internal_errors so that it can be restored.
	$use_internal_errors = libxml_use_internal_errors( true );

	$doc = new \DOMDocument( '1.0', 'utf-8' );
	$doc->loadHTML( mb_convert_encoding( $content, 'HTML-ENTITIES', 'UTF-8' ) );

	$finder = new \DOMXPath( $doc );

	/**
	 * Functionality to override onebox and extract links
	 */
	$oneboxes = $finder->query( "//*[contains(@class, 'onebox')]");
	foreach( $oneboxes as $onebox ) {
		$onebox_header = $onebox->getElementsByTagName('header')->item(0);
		if (!is_null($onebox_header)) {
			$link=$onebox_header->getElementsByTagName('a')->item(0)->getAttribute('href');
			$onebox_header->getElementsByTagName('a')->item(0)->nodeValue=$link;
			$link_anchor = $onebox_header->getElementsByTagName('a')->item(0);
			$onebox_parent = $onebox->parentNode;
			while ($onebox->hasChildNodes()) {
    		$onebox->removeChild($onebox->firstChild);
  		}
			$onebox -> appendChild($link_anchor);
		}

  }

	/**
	 * Functionality to remove images from replies
	 */
	$comments = $doc->getElementsByTagName('p');
	foreach( $comments as $comment ) {
		$images = $comment->getElementsByTagName('img');
		foreach( $images as $image ) {
				$img_link = $image->getAttribute('src');
				$img_anchor = $doc->createElement('a', $img_link);
				$img_parent=$image->parentNode;
				$img_parent->replaceChild($img_anchor,$image);
		}
  }

	// Clear the libxml error buffer.
	libxml_clear_errors();
	// Restore the previous value of libxml_use_internal_errors.
	libxml_use_internal_errors( $use_internal_errors );

	$parsed = $doc->saveHTML( $doc->documentElement );

	// Remove DOCTYPE, html, and body tags that have been added to the DOMDocument.
	$parsed = preg_replace( '~<(?:!DOCTYPE|/?(?:html|body))[^>]*>\s*~i', '', $parsed );

	return $parsed;
}

/*-----------------------------------------------------------------------------------*/
/* Shortening content of Discourse replies below Wordpress posts
/*-----------------------------------------------------------------------------------*/

add_filter( 'wpdc_comment_body', 'wpdc_custom_comment_body_truncate' );
function wpdc_custom_comment_body_truncate( $content ) {
	// Truncate the next
	$new_content = truncate(
		$content, 1000,
		array(
			'html' => true,
			'ending' => '<strong><a href="{comment_url}">... Continue reading in our forum</a></strong>')
	);

	return '<p>' . $new_content . '</p>';

}

/**
* Truncates text.
*
* Cuts a string to the length of $length and replaces the last characters
* with the ending if the text is longer than length.
*
* ### Options:
*
* - `ending` Will be used as Ending and appended to the trimmed string
* - `exact` If false, $text will not be cut mid-word
* - `html` If true, HTML tags would be handled correctly
*
* @param string  $text String to truncate.
* @param integer $length Length of returned string, including ellipsis.
* @param array $options An array of html attributes and options.
* @return string Trimmed string.
* @access public
* @link http://book.cakephp.org/view/1469/Text#truncate-1625
*/

function truncate($text, $length = 100, $options = array()) {
    $default = array(
        'ending' => '...', 'exact' => true, 'html' => false
    );
    $options = array_merge($default, $options);
    extract($options);

    if ($html) {
        if (mb_strlen(preg_replace('/<.*?>/', '', $text)) <= $length) {
            return $text;
        }
        $totalLength = mb_strlen(strip_tags($ending));
        $openTags = array();
        $truncate = '';

        preg_match_all('/(<\/?([\w+]+)[^>]*>)?([^<>]*)/', $text, $tags, PREG_SET_ORDER);
        foreach ($tags as $tag) {
            if (!preg_match('/img|br|input|hr|area|base|basefont|col|frame|isindex|link|meta|param/s', $tag[2])) {
                if (preg_match('/<[\w]+[^>]*>/s', $tag[0])) {
                    array_unshift($openTags, $tag[2]);
                } else if (preg_match('/<\/([\w]+)[^>]*>/s', $tag[0], $closeTag)) {
                    $pos = array_search($closeTag[1], $openTags);
                    if ($pos !== false) {
                        array_splice($openTags, $pos, 1);
                    }
                }
            }
            $truncate .= $tag[1];

            $contentLength = mb_strlen(preg_replace('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|&#x[0-9a-f]{1,6};/i', ' ', $tag[3]));
            if ($contentLength + $totalLength > $length) {
                $left = $length - $totalLength;
                $entitiesLength = 0;
                if (preg_match_all('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|&#x[0-9a-f]{1,6};/i', $tag[3], $entities, PREG_OFFSET_CAPTURE)) {
                    foreach ($entities[0] as $entity) {
                        if ($entity[1] + 1 - $entitiesLength <= $left) {
                            $left--;
                            $entitiesLength += mb_strlen($entity[0]);
                        } else {
                            break;
                        }
                    }
                }

                $truncate .= mb_substr($tag[3], 0 , $left + $entitiesLength);
                break;
            } else {
                $truncate .= $tag[3];
                $totalLength += $contentLength;
            }
            if ($totalLength >= $length) {
                break;
            }
        }
    } else {
        if (mb_strlen($text) <= $length) {
            return $text;
        } else {
            $truncate = mb_substr($text, 0, $length - mb_strlen($ending));
        }
    }
    if (!$exact) {
        $spacepos = mb_strrpos($truncate, ' ');
        if (isset($spacepos)) {
            if ($html) {
                $bits = mb_substr($truncate, $spacepos);
                preg_match_all('/<\/([a-z]+)>/', $bits, $droppedTags, PREG_SET_ORDER);
                if (!empty($droppedTags)) {
                    foreach ($droppedTags as $closingTag) {
                        if (!in_array($closingTag[1], $openTags)) {
                            array_unshift($openTags, $closingTag[1]);
                        }
                    }
                }
            }
            $truncate = mb_substr($truncate, 0, $spacepos);
        }
    }

    if ($html) {
        foreach ($openTags as $tag) {
            $truncate .= '</'.$tag.'>';
        }
    }

	$truncate .= $ending;
    return $truncate;
}

?>
