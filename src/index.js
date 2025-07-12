import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { 
    PanelBody, 
    TextControl,
    Button 
} from '@wordpress/components';
import { 
    InspectorControls,
    useBlockProps 
} from '@wordpress/block-editor';
import { useState, useEffect } from '@wordpress/element';

registerBlockType('ttp/directions', {
    title: __('TTP Directions', 'ttp-directions'),
    icon: 'location-alt',
    category: 'widgets',
    attributes: {
        address: {
            type: 'string',
            default: ''
        },
        gpsCoordinates: {
            type: 'string',
            default: ''
        },
        latitude: {
            type: 'number',
            default: 0
        },
        longitude: {
            type: 'number',
            default: 0
        }
    },

    edit: ({ attributes, setAttributes }) => {
        const { address, gpsCoordinates } = attributes;
        const [autocomplete, setAutocomplete] = useState(null);
        const [addressInput, setAddressInput] = useState(null);

        const blockProps = useBlockProps({
            className: 'ttp-directions-block-editor'
        });

        useEffect(() => {
            if (window.google && window.google.maps && addressInput && !autocomplete) {
                const autocompleteInstance = new window.google.maps.places.Autocomplete(addressInput, {
                    types: ['address']
                });

                autocompleteInstance.addListener('place_changed', () => {
                    const place = autocompleteInstance.getPlace();
                    if (place.geometry) {
                        const lat = place.geometry.location.lat();
                        const lng = place.geometry.location.lng();
                        const gpsString = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
                        
                        setAttributes({
                            address: place.formatted_address,
                            gpsCoordinates: gpsString,
                            latitude: lat,
                            longitude: lng
                        });
                    }
                });

                setAutocomplete(autocompleteInstance);
            }
        }, [addressInput, autocomplete, setAttributes]);

        const handleAddressChange = (value) => {
            setAttributes({ address: value });
        };

        const handleManualGeocode = () => {
            if (window.google && window.google.maps && address) {
                const geocoder = new window.google.maps.Geocoder();
                geocoder.geocode({ address: address }, (results, status) => {
                    if (status === 'OK' && results[0]) {
                        const lat = results[0].geometry.location.lat();
                        const lng = results[0].geometry.location.lng();
                        const gpsString = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
                        
                        setAttributes({
                            gpsCoordinates: gpsString,
                            latitude: lat,
                            longitude: lng
                        });
                    }
                });
            }
        };

        return (
            <div {...blockProps}>
                <InspectorControls>
                    <PanelBody title={__('Directions Settings', 'ttp-directions')}>
                        <Button 
                            isPrimary 
                            onClick={handleManualGeocode}
                            disabled={!address}
                        >
                            {__('Update GPS Coordinates', 'ttp-directions')}
                        </Button>
                    </PanelBody>
                </InspectorControls>

                <div className="ttp-directions-content">
                    <div className="ttp-directions-row">
                        <div className="ttp-address-field">
                            <label>{__('Address:', 'ttp-directions')}</label>
                            <input
                                type="text"
                                value={address}
                                onChange={(e) => handleAddressChange(e.target.value)}
                                placeholder={__('Enter address...', 'ttp-directions')}
                                ref={setAddressInput}
                                className="address-input"
                            />
                        </div>
                        <div className="ttp-gps-field">
                            <label>{__('GPS:', 'ttp-directions')}</label>
                            <input
                                type="text"
                                value={gpsCoordinates}
                                readOnly
                                placeholder={__('GPS coordinates will appear here', 'ttp-directions')}
                                className="gps-input"
                            />
                        </div>
                    </div>
                </div>
            </div>
        );
    },

    save: ({ attributes }) => {
        const { address, gpsCoordinates } = attributes;
        const blockProps = useBlockProps.save({
            className: 'ttp-directions-block'
        });

        return (
            <div {...blockProps}>
                <div className="ttp-directions-content">
                    <div className="ttp-directions-row">
                        <div className="ttp-address-field">
                            <label>Address:</label>
                            <div className="address-display">{address}</div>
                        </div>
                        <div className="ttp-gps-field">
                            <label>GPS:</label>
                            <div className="gps-display">{gpsCoordinates}</div>
                        </div>
                    </div>
                </div>
            </div>
        );
    }
});
