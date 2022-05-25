jQuery(function ($) { // DOM is now ready and jQuery's $ alias sandboxed
    const embed = $("#twitch-embed");

    // Display Twitch embed
    if (embed.length > 0) {
        const channelName = embed.data('channel');
        const parentDomain = embed.data('parent-channel')
        new Twitch.Embed("twitch-embed", {
            width: '100%',
            height: 480,
            theme: 'dark',
            channel: channelName,
            parent: parentDomain
        });
    }
});