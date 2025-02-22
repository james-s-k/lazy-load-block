import { registerBlockType } from '@wordpress/blocks';
import metadata from '../block.json';
import Edit from './edit';
import { InnerBlocks } from '@wordpress/block-editor';

// Import styles
import './style.scss';
import './editor.scss';

const settings = {
    edit: Edit,
    save: () => <InnerBlocks.Content />
};

// Register the block
registerBlockType(metadata.name, settings);

// Export for potential use by other modules
export { metadata, settings };
export const name = metadata.name; 