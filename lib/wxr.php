<?php
// Use Core's WXR version, since we use core's format.
define( 'WXR_VERSION', '1.2' );

/**
 * Wrap given string in XML CDATA tag.
 *
 * @since 2.1.0
 *
 * @param string $str String to wrap in XML CDATA tag.
 * @return string
 */
function dcg_wxr_cdata( $str ) {
	if ( seems_utf8( $str ) == false )
		$str = utf8_encode( $str );

	$str = '<![CDATA[' . str_replace( ']]>', ']]]]><![CDATA[>', $str ) . ']]>';

	return $str;
}

/**
 * Generate a category node for the XML export
 */
function dcg_wxr_category( $cat, $term_id ) {
?>
	<wp:category>
		<wp:term_id><?php echo $term_id; ?></wp:term_id>
		<wp:category_nicename><?php echo sanitize_title( $cat ); ?></wp:category_nicename>
		<wp:category_parent><?php echo ''; ?></wp:category_parent>
		<?php echo '<wp:cat_name>' . dcg_wxr_cdata( $cat ) . '</wp:cat_name>'; ?>
	</wp:category>
<?php
}