/**
 * WordPress dependencies for block editor functionality
 */
import { useBlockProps, useInnerBlocksProps, InspectorControls } from '@wordpress/block-editor';
import { useEffect } from '@wordpress/element';
import { PanelBody, SelectControl, RangeControl, ColorPalette, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Default template when block is first added
 */
const TEMPLATE = [
    ['core/paragraph', {}]
];

/**
 * Edit component for the Lazy Load Block
 * Handles the block's behavior in the editor
 *
 * @param {Object} props               Block props
 * @param {Object} props.attributes    Block attributes
 * @param {Function} props.setAttributes Function to update attributes
 * @param {string} props.clientId     Unique block ID in editor
 */
export default function Edit({ attributes, setAttributes, clientId }) {
    // Set unique ID for block only if not already set
    useEffect(() => {
        const uniqueId = `lazy-block-${clientId}`;
        if (!attributes.dataBlockId || attributes.dataBlockId !== uniqueId) {
            setAttributes({ dataBlockId: uniqueId });
        }
    }, [clientId, attributes.dataBlockId]);

    // Set up block wrapper props with required class
    const blockProps = useBlockProps({
        className: 'wp-block-strive-lazy-load-block lazy-load-block'
    });

    // Configure inner blocks behavior
    const innerBlocksProps = useInnerBlocksProps(
        blockProps,
        {
            template: TEMPLATE,
            templateLock: false
        }
    );

    /**
     * Available animation options for content reveal
     * Each option corresponds to a CSS animation class
     */
    const animationOptions = [
        { label: __('Fade', 'lazy-load-block'), value: 'fade' },
        { label: __('Slide Up', 'lazy-load-block'), value: 'slide-up' },
        { label: __('Slide Down', 'lazy-load-block'), value: 'slide-down' },
        { label: __('Slide Left', 'lazy-load-block'), value: 'slide-left' },
        { label: __('Slide Right', 'lazy-load-block'), value: 'slide-right' },
        { label: __('Scale Up', 'lazy-load-block'), value: 'scale-up' },
        { label: __('Scale Down', 'lazy-load-block'), value: 'scale-down' }
    ];

    return (
        <>
            {/* Block Settings Sidebar */}
            <InspectorControls>
                {/* Animation Settings Panel */}
                <PanelBody title={__('Animation Settings', 'lazy-load-block')}>
                    <SelectControl
                        label={__('Animation Type', 'lazy-load-block')}
                        value={attributes.animation ?? 'fade'}
                        options={animationOptions}
                        onChange={(animation) => setAttributes({ animation })}
                    />
                    <RangeControl
                        label={__('Animation Duration (ms)', 'lazy-load-block')}
                        value={attributes.animationDuration ?? 300}
                        onChange={(animationDuration) => setAttributes({ animationDuration })}
                        min={100}
                        max={6000}
                        step={50}
                    />
                    <RangeControl
                        label={__('Loading Trigger Offset (px)', 'lazy-load-block')}
                        help={__('Distance from viewport when loading begins', 'lazy-load-block')}
                        value={attributes.loadingOffset ?? 100}
                        onChange={(loadingOffset) => setAttributes({ loadingOffset })}
                        min={0}
                        max={1000}
                        step={10}
                    />
                </PanelBody>
                
                {/* Loading Spinner Settings Panel */}
                <PanelBody title={__('Spinner Settings', 'lazy-load-block')}>
                    <ToggleControl
                        label={__('Show Loading Spinner', 'lazy-load-block')}
                        checked={attributes.showSpinner ?? true}
                        onChange={(showSpinner) => setAttributes({ showSpinner })}
                    />
                    
                    {/* Show spinner customization options when enabled */}
                    {attributes.showSpinner && (
                        <>
                            {/* Live spinner preview with hover animation */}
                            <div className="llb-spinner-preview">
                                <div 
                                    className="llb-spinner-container"
                                    style={{
                                        width: `${attributes.spinnerSize}px`,
                                        height: `${attributes.spinnerSize}px`
                                    }}
                                >
                                    <div 
                                        className="llb-spinner"
                                        style={{
                                            borderWidth: `${attributes.spinnerBorderWidth}px`,
                                            borderStyle: 'solid',
                                            borderTopColor: attributes.spinnerPrimaryColor,
                                            borderRightColor: attributes.spinnerSecondaryColor,
                                            borderBottomColor: attributes.spinnerSecondaryColor,
                                            borderLeftColor: attributes.spinnerSecondaryColor,
                                            width: '100%',
                                            height: '100%'
                                        }}
                                    />
                                </div>
                                <span className="llb-spinner-label">
                                    {__(' Hover to preview animation', 'lazy-load-block')}
                                </span>
                            </div>

                            {/* Spinner size controls */}
                            <RangeControl
                                label={__('Spinner Size (px)', 'lazy-load-block')}
                                value={attributes.spinnerSize ?? 40}
                                onChange={(spinnerSize) => setAttributes({ spinnerSize })}
                                min={20}
                                max={100}
                                step={2}
                            />
                            <RangeControl
                                label={__('Border Width (px)', 'lazy-load-block')}
                                value={attributes.spinnerBorderWidth ?? 4}
                                onChange={(spinnerBorderWidth) => setAttributes({ spinnerBorderWidth })}
                                min={2}
                                max={10}
                                step={1}
                            />

                            {/* Spinner color controls */}
                            <div className="llb-color-control">
                                <label>{__('Primary Color (Spinning)', 'lazy-load-block')}</label>
                                <ColorPalette
                                    value={attributes.spinnerPrimaryColor ?? '#000000'}
                                    onChange={(color) => setAttributes({ spinnerPrimaryColor: color })}
                                    disableCustomColors={false}
                                    clearable={false}
                                />
                            </div>
                            <div className="llb-color-control">
                                <label>{__('Secondary Color (Static)', 'lazy-load-block')}</label>
                                <ColorPalette
                                    value={attributes.spinnerSecondaryColor ?? '#e0e0e0'}
                                    onChange={(color) => setAttributes({ spinnerSecondaryColor: color })}
                                    disableCustomColors={false}
                                    clearable={false}
                                />
                            </div>
                        </>
                    )}
                </PanelBody>
            </InspectorControls>

            {/* Block Content Area */}
            <div {...innerBlocksProps} />
        </>
    );
}