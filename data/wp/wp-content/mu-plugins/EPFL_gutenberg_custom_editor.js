const hideBlockManager = () => {
    // Select the node that will be observed for mutations
    const targetNodes = document.getElementsByClassName('edit-post-more-menu');

    if (targetNodes.length) {
        const targetNode = targetNodes[0];

        const config = { attributes: false, childList: true, subtree: false };

        // Callback function to execute when mutations are observed
        const callback = function(mutationsList, observer) {
            for(let mutation of mutationsList) {
                if (mutation.type === 'childList') {
                    let blockManagerElement = $('button.components-button:contains("Block Manager")');
                    if (blockManagerElement.length) {
                        blockManagerElement.hide()
                    }
                }
            }
        };

        // Create an observer instance linked to the callback function
        const observer = new MutationObserver(callback);
        // Start observing the target node for configured mutations
        observer.observe(targetNode, config);
    }
}

wp.domReady( function() {
    hideBlockManager();
});
