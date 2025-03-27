document.addEventListener('DOMContentLoaded', function() {
    // Enhanced card hover animations
    const cards = document.querySelectorAll('.info-card');
    cards.forEach(card => {
        card.addEventListener('mouseenter', () => {
            card.classList.add('animate__animated', 'animate__rubberBand', 'animate__faster');
            card.style.transform = 'scale(1.03)';
            card.style.boxShadow = '0 10px 25px rgba(0, 0, 0, 0.2)';
            card.style.zIndex = '10';
        });
        card.addEventListener('mouseleave', () => {
            card.classList.remove('animate__animated', 'animate__rubberBand', 'animate__faster');
            card.style.transform = 'scale(1)';
            card.style.boxShadow = '0 4px 15px rgba(0, 0, 0, 0.1)';
            card.style.zIndex = '1';
        });
    });

    // Enhanced button animations
    const buttons = document.querySelectorAll('.sidebar-buttons button, .btn-login');
    buttons.forEach(button => {
        button.addEventListener('mouseenter', () => {
            button.classList.add('animate__animated', 'animate__pulse', 'animate__faster');
            button.style.transform = 'scale(1.05) translateX(5px)';
        });
        button.addEventListener('mouseleave', () => {
            button.classList.remove('animate__animated', 'animate__pulse', 'animate__faster');
            button.style.transform = 'scale(1) translateX(0)';
        });
        button.addEventListener('click', () => {
            button.classList.add('animate__animated', 'animate__bounceOut', 'animate__faster');
            setTimeout(() => {
                button.classList.remove('animate__animated', 'animate__bounceOut', 'animate__faster');
            }, 500);
        });
    });

    // Enhanced idle animations
    let idleTimer;
    function resetIdleTimer() {
        clearTimeout(idleTimer);
        idleTimer = setTimeout(() => {
            document.querySelectorAll('.info-card').forEach((card, index) => {
                if (!card.matches(':hover')) {
                    setTimeout(() => {
                        card.classList.add('animate__animated', 'animate__heartBeat');
                        setTimeout(() => {
                            card.classList.remove('animate__animated', 'animate__heartBeat');
                        }, 1000);
                    }, index * 200);
                }
            });
            resetIdleTimer();
        }, 45000); // 45 seconds
    }
    
    // Start idle timer
    document.addEventListener('mousemove', resetIdleTimer);
    document.addEventListener('keypress', resetIdleTimer);
    resetIdleTimer();
});
