<?php
/**
 * Functionality to override onebox and extract links
 */
add_filter( 'wpdc_comment_body', 'wpdc_custom_comment_body' );
function wpdc_custom_comment_body( $content ) {
	// Allows parsing misformed html. Save the previous value of libxml_use_internal_errors so that it can be restored.
	$use_internal_errors = libxml_use_internal_errors( true );

	$doc = new \DOMDocument( '1.0', 'utf-8' );
	$doc->loadHTML( mb_convert_encoding( $content, 'HTML-ENTITIES', 'UTF-8' ) );

	$finder = new \DOMXPath( $doc );
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

	// Clear the libxml error buffer.
	libxml_clear_errors();
	// Restore the previous value of libxml_use_internal_errors.
	libxml_use_internal_errors( $use_internal_errors );

	$parsed = $doc->saveHTML( $doc->documentElement );

	// Remove DOCTYPE, html, and body tags that have been added to the DOMDocument.
	$parsed = preg_replace( '~<(?:!DOCTYPE|/?(?:html|body))[^>]*>\s*~i', '', $parsed );

	return $parsed;
}

?>
