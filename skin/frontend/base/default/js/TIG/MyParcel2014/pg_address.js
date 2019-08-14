/**
 *                  ___________       __            __
 *                  \__    ___/____ _/  |_ _____   |  |
 *                    |    |  /  _ \\   __\\__  \  |  |
 *                    |    | |  |_| ||  |   / __ \_|  |__
 *                    |____|  \____/ |__|  (____  /|____/
 *                                              \/
 *          ___          __                                   __
 *         |   |  ____ _/  |_   ____ _______   ____    ____ _/  |_
 *         |   | /    \\   __\_/ __ \\_  __ \ /    \ _/ __ \\   __\
 *         |   ||   |  \|  |  \  ___/ |  | \/|   |  \\  ___/ |  |
 *         |___||___|  /|__|   \_____>|__|   |___|  / \_____>|__|
 *                  \/                           \/
 *                  ________
 *                 /  _____/_______   ____   __ __ ______
 *                /   \  ___\_  __ \ /  _ \ |  |  \\____ \
 *                \    \_\  \|  | \/|  |_| ||  |  /|  |_| |
 *                 \______  /|__|    \____/ |____/ |   __/
 *                        \/                       |__|
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Creative Commons License.
 * It is available through the world-wide-web at this URL:
 * http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to servicedesk@tig.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact servicedesk@tig.nl for more information.
 *
 * @copyright   Copyright (c) 2014 Total Internet Group B.V. (http://www.tig.nl)
 * @license     http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 */

var MyParcelPgAddress = new Class.create();
MyParcelPgAddress.prototype = {
    options  : null,
    fieldset : null,
    address  : false,
    _isDebug : null,

    /**
     * @constructor
     */
    initialize : function (fieldset, options) {
        options = Object.extend({
            debug : false
        }, options || {});

        this.setOptions(options);
        this.setFieldset(fieldset);
    },

    setOptions : function(options) {
        this.options = options;

        return this;
    },

    getOptions : function() {
        return this.options;
    },

    setFieldset : function(fieldset) {
        this.fieldset = fieldset;

        return this;
    },

    getFieldset : function() {
        return this.fieldset;
    },

    setAddress : function(address) {
        address = Object.extend({
            firstname      : '-',
            lastname       : '-',
            name           : '-',
            street         : '-',
            housenumber    : '-',
            housenumberadd : '-',
            postalcode     : '-',
            city           : '-',
            country        : '-',
            phone          : '-'
        }, address || {});

        if (address['postalcodenum'] && address['postalcodealpha']) {
            address.postalcode = address['postalcodenum'] + address['postalcodealpha'];
        }

        this.address = address;

        return this;
    },

    getAddress : function() {
        return this.address;
    },

    isDebug : function() {
        if (this._isDebug !== null) {
            return this._isDebug;
        }

        var options = this.getOptions();
        if (options.debug == true) {
            this._isDebug = true;
            return true;
        }

        this._isDebug = false;
        return false;
    },

    updateFieldset : function() {
        var address = this.getAddress();
        if (!address) {
            if (this.isDebug()) {
                console.error('Missing address data');
            }
            return this;
        }

        var fieldset = this.getFieldset();
        var addressValue;
        fieldset.select('input').each(function(element) {
            var addressKey = element.readAttribute('data-tig-myparcel-address-key');
            if (!addressKey) {
                return;
            }

            addressValue = address[addressKey];
            if (!addressValue) {
                if (this.isDebug()) {
                    console.error('No data for field ' + addressKey);
                }
                addressValue = '-';
            }
            element.setValue(addressValue)
        }.bind(this));

        // populate info div placeholder
        var pginfo = $('pginfodiv');
        if(pginfo) {
            var pgstreet1 = address['street'] + ' ' + address['housenumber'] + address['housenumberadd'];
            var pgstreet2 = address['postalcode'] + ' ' + address['city'] + ' (' + address['country'] + ')';
            pginfo.innerHTML = '<p>' + address['name'] + '<br/>' + pgstreet1 + '<br/>' + pgstreet2 + '</p>';
        }

        return this;
    }
};