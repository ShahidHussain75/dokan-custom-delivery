/* global postalCodeData, jQuery, wp */
(function($) {
    'use strict';
 
    // Cache DOM elements and state
    const cache = {
        $body: null,
        $popup: null,
        $overlay: null,
        initialized: false,
        isManualOpen: false  
    };

    // Helper for translation
    function __(text, domain) {
        return wp && wp.i18n && wp.i18n.__ ? wp.i18n.__(text, domain) : text;
    }

    // Debounce function for performance
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Update cart without page reloa

    // Build options HTML efficiently
    function buildOptionsHtml() {
        const options = [`<option value="">${postalCodeData.selectLabel}</option>`];
        
        Object.entries(postalCodeData.vendorPostalPrices).forEach(([areaId, entry]) => {
            const stateText = entry.state ? ` (${entry.state})` : '';
            const displayText = `${entry.postal_code} - ${entry.area_name}${stateText}`;
            const labelText = `${entry.area_name}${stateText}`;
            
            options.push(
                `<option value="${entry.postal_code}" data-label="${labelText}">${displayText}</option>`
            );
        });
        
        return options.join('');
    }

    // Show error message to user
    function showGlobalError(message) {
        // Remove any existing error
        $('.postal-global-error').remove();
        
        const errorHtml = `
            <div class="postal-global-error" style="position:fixed; top:0; left:0; width:100%; background:#dc3232; color:white; padding:15px; z-index:99999; text-align:center; box-shadow: 0 2px 5px rgba(0,0,0,0.2);">
                <span>${message}</span>
                <button onclick="$(this).parent().fadeOut()" style="background:none; border:none; color:white; float:right; font-size:18px; cursor:pointer; padding:0 10px;">&times;</button>
            </div>
        `;
        
        cache.$body.prepend(errorHtml);
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            $('.postal-global-error').fadeOut();
        }, 5000);
    }

    // Enhanced popup builder with better error handling
    function buildAndShowMandatoryPopup() {
        if (postalCodeData.debug) {
            console.log('Building mandatory popup for vendor:', postalCodeData.vendorId);
        }
 
        // Validate vendor data
        if ($.isEmptyObject(postalCodeData.vendorPostalPrices)) {
            if (postalCodeData.debug) { 
                console.error('Cannot show mandatory popup without vendor postal codes.'); 
            }
            showGlobalError(__('Error: Cannot load delivery areas for this vendor.', postalCodeData.textDomain));
            return false;
        }
 
			const wasManualOpen = cache.isManualOpen;
			closeMandatoryPopup();
			cache.isManualOpen = wasManualOpen;
 
        // Build popup HTML with improved styling and accessibility
        const popupHtml = `
            <div id="postal-popup-modal" class="postal-popup-modal" role="dialog" aria-labelledby="popup-title" aria-modal="true" >
                <h3 id="popup-title" style="margin-top: 0; color: #333;">${postalCodeData.promptTitle}
                    <button id="postal-popup-close" style="position: absolute; right: 0; top: -5px; background: none; border: none; font-size: 20px; cursor: pointer; color: #666;">&times;</button>
                </h3>
                <p style="margin-bottom: 20px;"><strong>${__('Bitte wählen Sie unten Ihre Lieferpostleitzahl aus, um fortzufahren.', postalCodeData.textDomain)}</strong></p>
                <select id="postal-selector-dropdown" aria-label="${__('Select postal code', postalCodeData.textDomain)}" style="width: 100%; padding: 12px; margin-bottom: 20px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                    ${buildOptionsHtml()}
                </select>
                <div class="postal-popup-feedback" style="min-height: 20px; margin-bottom: 15px; color: #dc3232; font-weight: 500;"></div>
                <button id="save-postal-code" class="button alt" style="padding: 12px 24px; margin-bottom:10px; background: #005959; " disabled >${__('Auswahl bestätigen', postalCodeData.textDomain)}</button>
                <button id="save-pickup" class="button" style="padding: 12px; background: #333333;">
                ${__('Abholung', postalCodeData.textDomain)}
            </button>
            </div>
            <div id="postal-popup-overlay" class="postal-popup-overlay" style="display: block; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 10000;"></div>
        `;
 
        cache.$body.append(popupHtml);
        
        // Cache popup elements
        cache.$popup = $('#postal-popup-modal');
        cache.$overlay = $('#postal-popup-overlay');
        
        // Focus on dropdown for accessibility and trigger initial change
        setTimeout(function() {
            const $dropdown = $('#postal-selector-dropdown');
            const $saveButton = $('#save-postal-code');

            $dropdown.focus();
            
            // Manual test of button enable
            if ($dropdown.val() && $dropdown.val() !== '') {     
                $saveButton.prop('disabled', false);
            }
            
            $dropdown.trigger('change');
        }, 200);
        
        bindPopupEvents();
        return true;
    }

    // Separate event binding for better organization
    function bindPopupEvents() {
        const $dropdown = $('#postal-selector-dropdown');
        const $saveButton = $('#save-postal-code');
        const $feedback = $('.postal-popup-feedback');
     	const $pickupButton = $('#save-pickup'); // New pickup button
      
        // Simple and direct dropdown change handler
        $dropdown.on('change input', function() {
            const selectedValue = $(this).val();
            const isValid = selectedValue && selectedValue.trim() !== '';
           
            // Force enable/disable the button
            if (isValid) {
                $saveButton.prop('disabled', false).removeClass('disabled');
            } else {
                $saveButton.prop('disabled', true).addClass('disabled');
            }
               
            $feedback.text(''); // Clear any previous feedback
        });
		   $pickupButton.on('click', function (e) {
				e.preventDefault();
				handlePickupSave($pickupButton, $dropdown, $feedback);
			});
				// Enhanced save button handler with force enable check
          $saveButton.on('click', function(e) {
            e.preventDefault();
              // Force check - ignore disabled state if we have a valid value
            const selectedValue = $dropdown.val();
            if (!selectedValue || selectedValue.trim() === '') {
                console.log('❌ No valid selection');
                $feedback.text(__('Please select a valid postal code.', postalCodeData.textDomain));
                return;
            }
               handlePostalCodeSave($dropdown, $saveButton, $feedback);
        });

        // Also bind to mousedown for immediate response
        $saveButton.on('mousedown', function() {
//             console.log('Save button mousedown - disabled:', $(this).prop('disabled'));
        });

        // Keyboard support
        $dropdown.on('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const selectedValue = $(this).val();
//                 console.log('Enter pressed, selected value:', selectedValue);
                if (selectedValue && selectedValue.trim() !== '') {
                    $saveButton.trigger('click');
                }
            }
        });

        // Force trigger change event multiple times to ensure it works
        setTimeout(function() {
//             console.log('=== FORCING INITIAL CHANGE EVENT ===');
            $dropdown.trigger('change');
            
            // Also try after a longer delay
            setTimeout(function() {
//                 console.log('=== SECOND FORCE CHANGE EVENT ===');
                $dropdown.trigger('change');
            }, 500);
        }, 100);
// Cross button handler - must be inside bindPopupEvents
$('#postal-popup-close').on('click', function(e) {
    e.preventDefault();
    
    if (cache.isManualOpen) {
        closeMandatoryPopup();
    } else {
    }
});
    }
// Handle pickup selection
function handlePickupSave($pickupButton, $dropdown, $feedback) {
    // UI feedback
    $feedback.text('').removeClass('error success');
    $pickupButton.prop('disabled', true).text('Speichern...');
    $dropdown.prop('disabled', true);

    if (postalCodeData.debug) {
        console.log('Saving pickup option...');
    }

    $.ajax({
        url: postalCodeData.ajaxUrl,
        type: 'POST',
        data: {
            action: 'set_user_pickup',
            security: postalCodeData.pickupNonce,
            is_pickup: true
        },
        timeout: 10000
    })
    .done(function(response) {
        if (response.success) {
            window.location.reload(); // Optional

            setTimeout(() => {
                closeMandatoryPopup();
            }, 800);
        } else {
            handleSaveError(response.data.message, $pickupButton, $dropdown, $feedback);
        }
    })
    .fail(function(jqXHR, textStatus) {
        let errorMessage = textStatus === 'timeout' ?
            __('Request timed out. Please try again.', postalCodeData.textDomain) :
            __('An error occurred. Please try again.', postalCodeData.textDomain);
        handleSaveError(errorMessage, $pickupButton, $dropdown, $feedback);
    });
}
    // Enhanced save handler with better error handling and no reload
    function handlePostalCodeSave($dropdown, $button, $feedback) {
        const $pickupButton = $('#save-pickup');
        const selectedCode = $dropdown.val();
        const selectedLabel = $dropdown.find('option:selected').data('label');
        $pickupButton.prop('disabled', true);
        // Validation
        if (!selectedCode || selectedCode === '') {
            $feedback.text(__('Please select a valid postal code.', postalCodeData.textDomain));
            return;
        }
 
        // UI feedback
        $feedback.text('').removeClass('error success');
        $button.prop('disabled', true).text(postalCodeData.cartUpdatingText);
        $dropdown.prop('disabled', true);
        
        if (postalCodeData.debug) {
            console.log('Saving postal code:', selectedCode, 'Label:', selectedLabel);
        }
       
        // AJAX request with improved error handling
        $.ajax({
            url: postalCodeData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'set_user_postal_code',
                security: postalCodeData.postalNonce,
                postal_code: selectedCode,
                postal_label: selectedLabel,
            },
            timeout: 10000, // 10 second timeout
            beforeSend: function() {
                if (postalCodeData.debug) {
                    console.log('Sending postal code save request...');
                }
            }
        })
        .done(function(response) {
            if (response.success) {
                if (postalCodeData.debug) { 
                    console.log('Postal code saved successfully:', response.data.new_code, 'for vendor:', response.data.vendor_id); 
                }
                
                // Update local data
                postalCodeData.currentUserPostal = response.data.new_code;
                $pickupButton.prop('disabled', false);
                // Show loading in cart and update cart
                $('body').trigger('update_checkout');
                $(document.body).trigger('wc_update_cart');
                $('body').trigger('updated_cart_totals');
                window.location.reload();
                
                // Close popup after short delay
                setTimeout(() => {
                    closeMandatoryPopup();
                }, 800);
 
            } else {
                handleSaveError(response.data.message, $button, $dropdown, $feedback);
                $pickupButton.prop('disabled', false);
            }
        })
        .fail(function(jqXHR, textStatus, errorThrown) {
            let errorMessage;
            
            if (textStatus === 'timeout') {
                errorMessage = __('Request timed out. Please try again.', postalCodeData.textDomain);
            } else if (textStatus === 'abort') {
                errorMessage = __('Request was cancelled.', postalCodeData.textDomain);
            } else {
                errorMessage = __('An error occurred. Please try again.', postalCodeData.textDomain);
            }
            
            if (postalCodeData.debug) { 
                console.error('AJAX Error:', textStatus, errorThrown, jqXHR.responseText); 
            }
            
            handleSaveError(errorMessage, $button, $dropdown, $feedback);
        });
    }

    // Handle save errors consistently
    function handleSaveError(message, $button, $dropdown, $feedback) {
        $feedback.text(message || __('An error occurred.', postalCodeData.textDomain))
                .css('color', '#dc3232');
        
        // Re-enable controls
        $button.prop('disabled', false).text(__('Auswahl bestätigen', postalCodeData.textDomain));
        $dropdown.prop('disabled', false).trigger('change');
    }
    // Optimized popup close function
    function closeMandatoryPopup() {
        if (cache.$popup && cache.$popup.length) {
            cache.$popup.remove();
        }
        if (cache.$overlay && cache.$overlay.length) {
            cache.$overlay.remove();
        }
        
        // Clear cache
        cache.isManualOpen = false;
        cache.$popup = null;
        cache.$overlay = null;
    }
   
    // Initialize the postal selector
    function initPostalSelector() {
        if (cache.initialized) return;
        
        cache.$body = $('body');
        cache.initialized = true;
        
        const currentVendorId = parseInt(postalCodeData.vendorId, 10);
        const postalCodeSelected = postalCodeData.currentUserPostal && postalCodeData.currentUserPostal !== '';
        console.log('Current postal data:', postalCodeData);
        if (postalCodeData.debug) {
            console.log('Initializing Postal Selector - Vendor ID:', currentVendorId, 'Postal Selected:', postalCodeSelected);
            console.log('Current postal data:', postalCodeData);
        }
 
        // CORE LOGIC: Show popup if no postal code is selected
        if (!postalCodeSelected && !postalCodeData.isPickup) {
            if (postalCodeData.debug) { 
                console.log('No postal code selected. Showing mandatory popup.'); 
            }
            cache.isManualOpen = false;
            buildAndShowMandatoryPopup();
        } else {
            if (postalCodeData.debug) { 
                console.log('Postal code already selected:', postalCodeData); 
            }
        }
       
    }
    // Enhanced document ready with error handling
    $(document).ready(function() {
        try {
            // Ensure required data exists
            if (typeof postalCodeData === 'undefined') {
                console.error('postalCodeData is not defined');
                return;
            }
            
            initPostalSelector();
            
        } catch (error) {
            console.error('Error initializing postal selector:', error);
            if (postalCodeData && postalCodeData.debug) {
                showGlobalError('Failed to initialize postal code selector. Please refresh the page.');
            }
        }
    });

    // Handle page visibility changes (user returns to tab)
    $(document).on('visibilitychange', function() {
        if (!document.hidden && postalCodeData.debug) {
            console.log('Page became visible - postal selector ready');
        }
    });

    // Expose public methods for external use if needed
    window.PostalSelector = {
        showPopup: buildAndShowMandatoryPopup,
        closePopup: closeMandatoryPopup,
        isInitialized: function() { return cache.initialized; },
        showPopupManually: function() {
            cache.isManualOpen = true;
            buildAndShowMandatoryPopup();
        }
    };
 
}(jQuery));