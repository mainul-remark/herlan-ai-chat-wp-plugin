(function($) {
    'use strict';

    // Generate a unique session ID per browser session
    let sessionId = sessionStorage.getItem('herlan_ai_session');
    if (!sessionId) {
        sessionId = 'sess_' + Math.random().toString(36).substr(2, 12);
        sessionStorage.setItem('herlan_ai_session', sessionId);
    }

    const productId = HerlanAI.product_id;
    let chipsHidden = false;

    // Open / Close modal
    $(document).on('click', '#herlan-ai-open', function() {
        $('#herlan-ai-modal').fadeIn(200);
        $('#herlan-ai-input').focus();
    });

    $(document).on('click', '#herlan-ai-close', function() {
        $('#herlan-ai-modal').fadeOut(200);
    });

    // Close on outside click
    $(document).on('click', '#herlan-ai-modal', function(e) {
        if ($(e.target).is('#herlan-ai-modal')) {
            $('#herlan-ai-modal').fadeOut(200);
        }
    });

    // Quick reply chips
    $(document).on('click', '.herlan-chip', function() {
        const text = $(this).text();
        sendMessage(text);
        hideChips();
    });

    // Send on button click
    $(document).on('click', '#herlan-ai-send', function() {
        const msg = $('#herlan-ai-input').val().trim();
        if (msg) { sendMessage(msg); hideChips(); }
    });

    // Send on Enter key
    $(document).on('keypress', '#herlan-ai-input', function(e) {
        if (e.which === 13) {
            const msg = $(this).val().trim();
            if (msg) { sendMessage(msg); hideChips(); }
        }
    });

    function hideChips() {
        if (!chipsHidden) {
            $('#herlan-ai-chips').slideUp(200);
            chipsHidden = true;
        }
    }

    // function sendMessage(message) {
    //     appendMessage('user', message);
    //     $('#herlan-ai-input').val('');
    //     showTyping();
    //
    //     $.ajax({
    //         url: HerlanAI.ajax_url,
    //         method: 'POST',
    //         contentType: 'application/json',
    //         data: JSON.stringify({
    //             product_id: productId,
    //             session_id: sessionId,
    //             message: message
    //         }),
    //         headers: { 'X-WP-Nonce': HerlanAI.nonce },
    //         success: function(res) {
    //             removeTyping();
    //             appendMessage('ai', res.reply);
    //         },
    //         error: function() {
    //             removeTyping();
    //             appendMessage('ai', "I'm sorry, I'm having trouble connecting right now. Please try again in a moment.");
    //         }
    //     });
    // }

    function sendMessage(message) {
        appendMessage('user', message);
        $('#herlan-ai-input').val('');

        // Create empty AI message bubble to stream into
        const $bubble = $('');
        $('#herlan-ai-messages').append($bubble);
        scrollToBottom();

        // Show typing dots briefly then start stream
        $bubble.html('');

        fetch(HerlanAI.ajax_url.replace('/message', '/stream'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': HerlanAI.nonce
            },
            body: JSON.stringify({
                product_id: parseInt(productId),
                session_id: sessionId,
                message: message
            })
        }).then(response => {
            const reader    = response.body.getReader();
            const decoder   = new TextDecoder();
            let fullText    = '';
            let firstToken  = true;

            function readChunk() {
                reader.read().then(({ done, value }) => {
                    if (done) return;
                    const lines = decoder.decode(value).split('\n');
                    lines.forEach(line => {
                        if (!line.startsWith('data: ')) return;
                        const data = line.slice(6).trim();
                        if (data === '[DONE]') return;
                        try {
                            const parsed = JSON.parse(data);
                            if (parsed.token) {
                                if (firstToken) {
                                    $bubble.html('');
                                    firstToken = false;
                                }
                                fullText += parsed.token;
                                $bubble.text(fullText);
                                scrollToBottom();
                            }
                        } catch(e) {}
                    });
                    readChunk();
                });
            }
            readChunk();
        }).catch(() => {
            $bubble.text("I'm having trouble connecting. Please try again.");
        });
    }

    function appendMessage(role, text) {
        const cls  = role === 'user' ? 'user' : 'ai';
        const $msg = $('').text(text);
        $('#herlan-ai-messages').append($msg);
        scrollToBottom();
    }

    function showTyping() {
        const $typing = $('');
        $('#herlan-ai-messages').append($typing);
        scrollToBottom();
    }

    function removeTyping() {
        $('#herlan-typing-indicator').remove();
    }

    function scrollToBottom() {
        const $msgs = $('#herlan-ai-messages');
        $msgs.scrollTop($msgs[0].scrollHeight);
    }

})(jQuery);