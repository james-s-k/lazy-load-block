/**
 * Frontend JavaScript for the Lazy Load Block
 * Handles intersection observation and dynamic content loading
 */
document.addEventListener("DOMContentLoaded", function () {
    // Track loaded blocks to prevent duplicate loading
    const loadedBlocks = new Set();

    // Create a single IntersectionObserver instance
    const observer = new IntersectionObserver((entries, obs) => {
        entries.forEach(entry => {
            if (!entry.isIntersecting) return;

            const block = entry.target;
            const blockId = block.getAttribute("data-block-id");

            // Skip if block is already loaded or currently loading
            if (loadedBlocks.has(blockId) || block.dataset.loading) {
                return;
            }

            // Mark block as loading and add to loaded set
            block.dataset.loading = "true";
            loadedBlocks.add(blockId);

            // Add loading spinner if enabled
            if (block.getAttribute('data-show-spinner') === 'true') {
                addSpinner(block);
            }

            loadBlock(block);
            obs.unobserve(block);

            // Disconnect observer if all blocks are loaded
            if (loadedBlocks.size === document.querySelectorAll(".wp-block-strive-lazy-load-block").length) {
                obs.disconnect();
            }
        });
    }, {
        rootMargin: '50px 0px',
        threshold: 0.1
    });

    // Observe all lazy load blocks
    document.querySelectorAll(".wp-block-strive-lazy-load-block").forEach(block => {
        observer.observe(block);
    });
});

/**
 * Adds a loading spinner to the block
 */
function addSpinner(block) {
    // Get all spinner attributes once with defaults
    const spinnerConfig = {
        size: parseInt(block.getAttribute('data-spinner-size')) || 40,
        border: parseInt(block.getAttribute('data-spinner-border')) || 4,
        primaryColor: block.getAttribute('data-spinner-primary') || '#c214bf',
        secondaryColor: block.getAttribute('data-spinner-secondary') || '#290529'
    };
    
    // Create spinner container with specified size
    const spinnerContainer = document.createElement("div");
    spinnerContainer.className = "llb-spinner-container";
    spinnerContainer.style.width = `${spinnerConfig.size}px`;
    spinnerContainer.style.height = `${spinnerConfig.size}px`;
    
    // Create and style the spinner element
    const spinner = document.createElement("div");
    spinner.className = "llb-spinner";
    spinner.style.width = '100%';
    spinner.style.height = '100%';
    spinner.style.borderWidth = `${spinnerConfig.border}px`;
    spinner.style.borderStyle = 'solid';
    spinner.style.borderTopColor = spinnerConfig.primaryColor;
    spinner.style.borderRightColor = spinnerConfig.secondaryColor;
    spinner.style.borderBottomColor = spinnerConfig.secondaryColor;
    spinner.style.borderLeftColor = spinnerConfig.secondaryColor;
    
    spinnerContainer.appendChild(spinner);
    block.appendChild(spinnerContainer);
}

/**
 * Loads block content via AJAX with retry functionality
 */
function loadBlock(block, retryCount = 0) {
    const MAX_RETRIES = 3;
    const RETRY_DELAY = 2000; // 2 seconds
    const blockId = block.getAttribute("data-block-id");
    
    // Make AJAX request to load block content
    const formData = new FormData();
    formData.append('action', 'lazy_load_block_content');
    formData.append('block_id', blockId);
    formData.append('nonce', lazyBlockAjax.nonce);

    // Create AbortController for timeout
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 10000); // 10 second timeout

    fetch(lazyBlockAjax.ajax_url, {
        method: 'POST',
        credentials: 'same-origin',
        body: formData,
        signal: controller.signal
    })
    .then(response => {
        clearTimeout(timeout);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success && data.content) {
            handleSuccessfulLoad(block, data);
        } else {
            throw new Error(data.message || 'Failed to load block content');
        }
    })
    .catch(error => {
        // Comment out error logging
        /*console.error('Error loading block content:', error);*/
        
        // Reset loading state
        block.dataset.loading = "false";
        loadedBlocks.delete(blockId);

        // Retry if under max attempts
        if (retryCount < MAX_RETRIES) {
            /*console.log(`Retrying load (${retryCount + 1}/${MAX_RETRIES})...`);*/
            setTimeout(() => {
                loadBlock(block, retryCount + 1);
            }, RETRY_DELAY);
        } else {
            /*console.error('Max retries reached, showing error message');*/
            showErrorState(block, 'Unable to load content. Try refreshing the page.');
        }
    });
}

/**
 * Handles successful block load
 */
function handleSuccessfulLoad(block, data) {
    // Create temporary container to parse content
    const temp = document.createElement('div');
    temp.innerHTML = data.content;

    // Handle embeds specially
    const embeds = temp.querySelectorAll('iframe[src*="youtube.com"], iframe[src*="vimeo.com"]');
    embeds.forEach(embed => {
        // Clean up iframe attributes
        const allowedAttributes = ['src', 'width', 'height', 'title', 'frameborder', 'allow'];
        Array.from(embed.attributes).forEach(attr => {
            if (!allowedAttributes.includes(attr.name)) {
                embed.removeAttribute(attr.name);
            }
        });
        
        // Set secure and privacy-focused attributes
        embed.setAttribute('loading', 'lazy');
        embed.setAttribute('frameborder', '0');
        embed.setAttribute('allow', 'encrypted-media; web-share');
    });

    // Remove loading state and spinner
    block.dataset.loading = "false";
    const spinner = block.querySelector('.llb-spinner-container');
    if (spinner) {
        spinner.remove();
    }

    // Add the loaded content to the block
    block.innerHTML = temp.innerHTML;

    // Add debug info if available
    if (data.debug) {
        const debugInfo = document.createElement('div');
        debugInfo.className = 'llb-debug-info';
        debugInfo.innerHTML = data.debug;
        block.appendChild(debugInfo);
    }

    // Ensure all required CSS variables are set
    if (lazyBlockAjax.cssVars) {
        Object.entries(lazyBlockAjax.cssVars).forEach(([variable, value]) => {
            document.documentElement.style.setProperty(variable, value);
        });
    }

    // Re-trigger WordPress block initialization
    if (typeof wp !== 'undefined' && wp.blocks && wp.blocks.getBlockTypes) {
        const galleries = block.querySelectorAll('.wp-block-gallery');
        galleries.forEach(gallery => {
            // Remove and re-add the gallery class to trigger WordPress's initialization
            const classes = gallery.className;
            gallery.className = '';
            void gallery.offsetWidth; // Force reflow
            gallery.className = classes;
        });
    }

    // Force a reflow before adding animation classes
    void block.offsetWidth;
    
    // Set animation duration from block attributes
    block.style.setProperty('--animation-duration', 
        `${block.getAttribute('data-animation-duration')}ms`);
    
    // Add loaded class to trigger animation
    block.classList.add("loaded");
}

/**
 * Shows error state in block
 */
function showErrorState(block, message = 'Failed to load content. Please refresh the page.') {
    const errorMessage = document.createElement('div');
    errorMessage.className = 'lazy-block-error';
    errorMessage.innerHTML = `
        <p>${message}</p>
        <button onclick="location.reload()">Refresh Page</button>
    `;
    block.innerHTML = '';
    block.appendChild(errorMessage);
    block.classList.add('error');
} 