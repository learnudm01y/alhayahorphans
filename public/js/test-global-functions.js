console.log('Testing global JavaScript functions...');

setTimeout(function() {
    console.log('Available functions after 1 second:');
    console.log('displayResults:', typeof window.displayResults);
    console.log('displayResultsAsTable:', typeof window.displayResultsAsTable);
    console.log('showRecordDetails:', typeof window.showRecordDetails);
    console.log('getStatusBadgeClass:', typeof window.getStatusBadgeClass);

    if (typeof window.displayResults === 'function' &&
        typeof window.displayResultsAsTable === 'function') {
        console.log('✅ All functions are available globally!');
    } else {
        console.log('❌ Some functions are missing!');
    }
}, 1000);
