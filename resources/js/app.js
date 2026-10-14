import './bootstrap';
import './products';

const logout_link = document.getElementById('logoutLink');
const logout_form = document.getElementById('logoutForm');

if (logout_link && logout_form) {
    logout_link.addEventListener('click', (event) => {
        event.preventDefault();
        logout_form.submit();
    });
}
