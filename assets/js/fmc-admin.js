/**
 * Flamingo Filter Plus - Admin filter dropdowns.
 *
 * Moves the hidden TLD/domain selects into the list table actions bar
 * and adds dynamic filtering so the domain dropdown only shows domains
 * matching the currently selected TLD.
 *
 * @param {Object} fmcFilterData Localized data from PHP.
 */
(function () {
	'use strict';

	if ( typeof fmcFilterData === 'undefined' ) {
		return;
	}

	var suffix = fmcFilterData.suffix;
	var filterLabel = fmcFilterData.filterLabel;

	var source = document.getElementById( 'fmc-filters-source-' + suffix );

	if ( ! source ) {
		return;
	}

	var tldSelect = document.getElementById( 'fmc-tld-filter-' + suffix );
	var domainSelect = document.getElementById( 'fmc-domain-filter-' + suffix );

	// Find the Filter button, or the Export button as fallback.
	var filterBtn = document.getElementById( 'post-query-submit' );
	var exportBtn = document.getElementById( 'export' );
	var anchor = filterBtn || exportBtn;

	if ( ! anchor ) {
		return;
	}

	var container = anchor.parentNode;

	// Create a Filter button if one does not exist.
	if ( ! filterBtn ) {
		filterBtn = document.createElement( 'input' );
		filterBtn.type = 'submit';
		filterBtn.id = 'post-query-submit';
		filterBtn.className = 'button';
		filterBtn.value = filterLabel;
		container.insertBefore( filterBtn, anchor );
	}

	container.insertBefore( tldSelect, filterBtn );
	container.insertBefore( domainSelect, filterBtn );

	tldSelect.style.display = '';
	domainSelect.style.display = '';

	source.parentNode.removeChild( source );

	// Dynamic domain filtering: when a TLD is selected, only show domains
	// matching that TLD in the domain dropdown.
	var domainOptions = [];
	var i;

	for ( i = 0; i < domainSelect.options.length; i++ ) {
		domainOptions.push( {
			value: domainSelect.options[ i ].value,
			text: domainSelect.options[ i ].text
		} );
	}

	tldSelect.addEventListener( 'change', function () {
		var selectedTld = tldSelect.value;
		var currentDomain = domainSelect.value;

		// Clear domain options.
		domainSelect.length = 0;

		for ( i = 0; i < domainOptions.length; i++ ) {
			var opt = domainOptions[ i ];

			// Always show the "All domains" placeholder (empty value).
			if ( ! opt.value ) {
				domainSelect.add( new Option( opt.text, opt.value ) );
				continue;
			}

			// If no TLD filter is active, show all domains.
			if ( ! selectedTld ) {
				domainSelect.add( new Option( opt.text, opt.value ) );
				continue;
			}

			// Only show domains ending with the selected TLD.
			if ( opt.value.endsWith( '.' + selectedTld ) ) {
				domainSelect.add( new Option( opt.text, opt.value ) );
			}
		}

		// Restore previous domain selection if still available.
		domainSelect.value = currentDomain;

		if ( domainSelect.selectedIndex === -1 ) {
			domainSelect.selectedIndex = 0;
		}
	} );
})();
