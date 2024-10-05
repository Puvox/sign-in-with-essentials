/**
 * All of the code for your public-facing JavaScript source
 * should reside in this file.
 *
 * @package Sign In With Essentials
 */

(function() {

	function appleHoverMsg() {
		const buttonDiv = document.querySelector( '#siwe-anchor-apple' );
		const hoverDiv = document.querySelector( '#apple-forbid-hidden-mail' );
		// on hover, set display:block to hoverDiv
		buttonDiv.addEventListener( 'mouseover', function() {
			hoverDiv.style.display = 'block';
		});
		// on mouseout, set display:none to hoverDiv
		buttonDiv.addEventListener( 'mouseout', function() {
			hoverDiv.style.display = 'none';
		});
	}


	window.addEventListener( 'load', function () {
		const form = document.querySelector( '#loginform' );
		const button = document.querySelector( '#siwe-container' );
		form.parentNode.insertBefore(button, form.nextSibling);

		appleHoverMsg();
	});
})();
