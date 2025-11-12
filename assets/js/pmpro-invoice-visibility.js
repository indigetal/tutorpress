(function() {
	'use strict';
	
	/**
	 * TutorPress: Show invoice section when PMPro is selected
	 * 
	 * Overrides Tutor LMS's default behavior of only showing invoices
	 * when native commerce engine is selected. PMPro users need invoice
	 * functionality since PMPro doesn't have built-in invoice generation.
	 * 
	 * @since 1.5.0
	 */
	
	// Constants
	const MONETIZATION_FIELD_SELECTOR = "[name='tutor_option[monetize_by]']";
	const INVOICE_BLOCK_SELECTOR = ".tutor-option-single-item.ecommerce_invoice";
	const ENGINES_WITH_INVOICE = ['tutor', 'pmpro'];
	
	/**
	 * Check if invoice section should be visible for given engine
	 * 
	 * @param {string} engine - The monetization engine identifier
	 * @return {boolean} True if invoice should be visible
	 */
	function shouldShowInvoice(engine) {
		return ENGINES_WITH_INVOICE.includes(engine);
	}
	
	/**
	 * Update invoice section visibility based on selected engine
	 * 
	 * @param {string} engine - The monetization engine identifier
	 * @param {HTMLElement} block - The invoice block element
	 */
	function updateInvoiceVisibility(engine, block) {
		if (shouldShowInvoice(engine)) {
			block.classList.remove('tutor-d-none');
		} else {
			block.classList.add('tutor-d-none');
		}
	}
	
	/**
	 * Initialize invoice visibility override
	 * Sets initial state and listens for monetization engine changes
	 */
	function init() {
		const monetizationField = document.querySelector(MONETIZATION_FIELD_SELECTOR);
		const invoiceBlock = document.querySelector(INVOICE_BLOCK_SELECTOR);
		
		// Graceful exit if elements not found
		if (!monetizationField || !invoiceBlock) {
			return;
		}
		
		// Set initial state based on current monetization engine
		updateInvoiceVisibility(monetizationField.value, invoiceBlock);
		
		// Listen for monetization engine changes
		monetizationField.addEventListener('change', function(e) {
			updateInvoiceVisibility(e.target.value, invoiceBlock);
		});
	}
	
	// Initialize when DOM is ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		// DOM already loaded, initialize immediately
		init();
	}
})();

