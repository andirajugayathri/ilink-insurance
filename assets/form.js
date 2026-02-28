function generateCaptcha() {
    const num1 = Math.floor(Math.random() * 20) + 1;
    const num2 = Math.floor(Math.random() * 20) + 1;
    const operators = ['+', '-', '*'];
    const operator = operators[Math.floor(Math.random() * operators.length)];

    let question;
    switch (operator) {
        case '+':
            correctAnswer = num1 + num2;
            question = `${num1} + ${num2}`;
            break;
        case '-':
            if (num1 >= num2) {
                correctAnswer = num1 - num2;
                question = `${num1} - ${num2}`;
            } else {
                correctAnswer = num2 - num1;
                question = `${num2} - ${num1}`;
            }
            break;
        case '*':
            const smallNum1 = Math.floor(Math.random() * 10) + 1;
            const smallNum2 = Math.floor(Math.random() * 10) + 1;
            correctAnswer = smallNum1 * smallNum2;
            question = `${smallNum1} × ${smallNum2}`;
            break;
    }

    const questionElement = document.getElementById('question');
    const mathVerificationElement = document.getElementById('math_verification');
    const correctAnswerElement = document.getElementById('correct_answer');
    
    if (questionElement) {
        questionElement.textContent = question;
    }
    if (mathVerificationElement) {
        mathVerificationElement.value = '';
    }
    if (correctAnswerElement) {
        correctAnswerElement.value = correctAnswer;
    }
    hideError('math_verification');
}

// Validation helpers
function validateEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

function validateAddress(address) {
    const addressRegex = /^[a-zA-Z0-9\s,'\-.#]+$/;
    return addressRegex.test(address);
}

function validateName(name) {
    const nameRegex = /^[a-zA-Z\s'-]{2,50}$/;
    return nameRegex.test(name);
}

function validatePhone(phone) {
    const phoneRegex = /^[\d\s\-+()]{8,10}$/;
    return phoneRegex.test(phone);
}

function validateOccupation(occupation) {
    const occupationRegex = /^[a-zA-Z\s'-]{2,50}$/;
    return occupationRegex.test(occupation);
}

function validateSubject(subject) {
    const subjectRegex = /^[a-zA-Z\s'-]{2,50}$/;
    return subjectRegex.test(subject);
}

function validateCompany(company){
    const companyRegex=/^[a-zA-Z\s'-]{2,50}$/;
    return companyRegex.test(company)
}

// Error display functions
function showError(fieldId, message) {
    const errorElement = document.getElementById(`${fieldId}-error`);
    const inputElement = document.getElementById(fieldId);
    if (errorElement) {
        errorElement.textContent = message;
        errorElement.style.display = 'block';
        errorElement.style.color = 'red';
    }
    if (inputElement) {
        inputElement.classList.add('is-invalid');
    }
}

function hideError(fieldId) {
    const errorElement = document.getElementById(`${fieldId}-error`);
    const inputElement = document.getElementById(fieldId);
    if (errorElement) {
        errorElement.style.display = 'none';
    }
    if (inputElement) {
        inputElement.classList.remove('is-invalid');
    }
}

// Validate individual field
function validateField(fieldId) {
    const field = document.getElementById(fieldId);
    if (!field) return true;

    const value = field.value.trim();
    let isValid = true;
    let message = '';

    switch (fieldId) {
        case 'name':
            if (!value) {
                isValid = false;
                message = 'Please enter your full name';
            } else if (!validateName(value)) {
                isValid = false;
                message = 'Name must contain only letters, spaces, apostrophes, or hyphens (2–50 characters)';
            }
            break;

        case 'subject':
            if (!value) {
                isValid = false;
                message = 'Please provide your subject';
            } else if (!validateSubject(value)) {
                isValid = false;
                message = 'Subject must contain only letters, spaces, apostrophes, or hyphens (2–50 characters)';
            }
            break;
        
        case 'occupation':
            if (!value) {
                isValid = false;
                message = 'Please provide your occupation';
            } else if (!validateOccupation(value)) {
                isValid = false;
                message = 'Occupation must contain only letters, spaces, apostrophes, or hyphens (2–50 characters)';
            }
            break;

        case 'email':
            if (!value) {
                isValid = false;
                message = 'Please enter your email address';
            } else if (!validateEmail(value)) {
                isValid = false;
                message = 'Please enter a valid email address';
            }
            break;
            
        case 'contact':
            if (!value) {
                isValid = false;
                message = 'Please enter your contact number';
            } else if (!validatePhone(value)) {
                isValid = false;
                message = 'Please enter a valid contact number (min 8 digits)';
            }
            break;
            
        case 'confirmEmail':
            const originalEmail = document.getElementById('email')?.value.trim() || '';
            if (!value) {
                isValid = false;
                message = 'Please confirm your email address';
            } else if (value !== originalEmail) {
                isValid = false;
                message = 'Email addresses do not match';
            }
            break;
            
        case 'city':
            if (!value) {
                isValid = false;
                message = 'Please enter your city';
            }
            break;
            
        case 'insurance':
            if (!value) {
                isValid = false;
                message = 'Please select insurance';
            }
            break;

        case 'address':
            if (!value) {
                isValid = false;
                message = 'Please provide an address';
            } else if (!validateAddress(value)) {
                isValid = false;
                message = 'Address contains invalid characters';
            }
            break;

        case 'company':
            if(!value){
                isValid=false;
                message='please provide your company name'
            }
            else if(!validateCompany(value)){
                isValid=false;
                message='please provide valid company name'
            }
            break;
            
        case 'math_verification':
            if (!value) {
                isValid = false;
                message = 'Please solve the math problem';
            } else if (parseInt(value) !== correctAnswer) {
                isValid = false;
                message = 'Incorrect answer. Please try again.';
            }
            break;
    }

    if (isValid) {
        hideError(fieldId);
    } else {
        showError(fieldId, message);
    }

    return isValid;
}

// Validate entire form - this is your main validation function
function validateContact() {
    const requiredFields = ['name', 'email', 'contact', 'city', 'insurance', 'math_verification', 'confirmEmail'];
    let formValid = true;

    requiredFields.forEach(fieldId => {
        if (!validateField(fieldId)) {
            formValid = false;
        }
    });
    return formValid;
}

// Function to handle form submission
function handleFormSubmit(event) {
    // Prevent default form submission
    event.preventDefault();
    
    // Run validation
    const isValid = validateContact();
    
    // Only allow form submission if validation passes
    if (isValid) {
        // If you want to actually submit the form after validation, uncomment the next line:
        event.target.submit();
        return true;
    }
    
    return false;
}

// Email confirmation functionality
function checkEmailMatch() {
    const originalEmail = document.getElementById('email')?.value.trim() || '';
    const confirmEmail = document.getElementById('confirmEmail')?.value.trim() || '';
    const emailMatchStatus = document.getElementById('emailMatchStatus');
    const emailMatchError = document.getElementById('emailMatchError');
    const confirmEmailInput = document.getElementById('confirmEmail');
    const sbmt = document.getElementById('sbmt'); // Submit button

    if (confirmEmail === '') {
        if (emailMatchStatus) {
            emailMatchStatus.classList.remove('show');
        }
        if (emailMatchError) emailMatchError.textContent = '';
        if (confirmEmailInput) {
            confirmEmailInput.classList.remove('verified', 'error');
        }
        if (sbmt) sbmt.style.backgroundColor = ''; // Reset button color
        return false;
    }

    if (originalEmail === confirmEmail) {
        if (emailMatchStatus) {
            emailMatchStatus.classList.add('show');
        }
        if (emailMatchError) emailMatchError.textContent = '';
        if (confirmEmailInput) {
            confirmEmailInput.classList.add('verified');
            confirmEmailInput.classList.remove('error');
        }
        if (sbmt) sbmt.style.backgroundColor = '#ff5722'; // Change color when match ✅
        return true;
    } else {
        if (emailMatchStatus) {
            emailMatchStatus.classList.remove('show');
        }
        if (emailMatchError) emailMatchError.textContent = 'Email addresses do not match';
        if (confirmEmailInput) {
            confirmEmailInput.classList.add('error');
            confirmEmailInput.classList.remove('verified');
        }
        if (sbmt) sbmt.style.backgroundColor = ''; // Change color when not match ❌
        return false;
    }
}


// Generate CAPTCHA on page load
document.addEventListener('DOMContentLoaded', () => {
    generateCaptcha();

    // Find the form and add submit event listener
    const form = document.querySelector('form');
    if (form) {
        // Prevent form submission and validate instead
        form.addEventListener('submit', handleFormSubmit);
        
        // Also prevent any other submit attempts
        form.onsubmit = handleFormSubmit;
    }

    // Real-time validation on blur and input events
    const fields = ['name', 'email', 'contact', 'city', 'insurance', 'math_verification', 'address', 'occupation', 'subject', 'company', 'confirmEmail'];
    fields.forEach(fieldId => {
        const input = document.getElementById(fieldId);
        if (input) {
            // Validate on blur (when user leaves field)
            input.addEventListener('blur', () => validateField(fieldId));
            
            // Also validate on input (as user types) - helps with auto-fill
            input.addEventListener('input', () => {
                // Small delay to allow auto-fill to complete
                setTimeout(() => validateField(fieldId), 100);
            });
            
            // Validate when field value changes (catches auto-fill)
            input.addEventListener('change', () => validateField(fieldId));
        }
    });

    // Email confirmation real-time validation
    const emailInput = document.getElementById('email');
    const sbmt= document.getElementById('sbmt');
    const confirmEmailInput = document.getElementById('confirmEmail');

    
    if (emailInput) {
        emailInput.addEventListener('input', checkEmailMatch);
    }
    
    if (confirmEmailInput) {
        confirmEmailInput.addEventListener('input', checkEmailMatch);
        // sbmt.style.backgroundColor='black'
    }

    // Additional protection: Monitor for auto-fill detection
    const observer = new MutationObserver(() => {
        // Check if form has been auto-filled and validate
        fields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field && field.value.trim()) {
                setTimeout(() => validateField(fieldId), 100);
            }
        });
    });

    // Start observing changes to form
    if (form) {
        observer.observe(form, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['value']
        });
    }
});

// Add regenerate CAPTCHA button
document.addEventListener('DOMContentLoaded', () => {
    const questionElement = document.getElementById('question');
    if (questionElement && !document.getElementById('regenerate-captcha')) {
        const regenBtn = document.createElement('button');
        regenBtn.id = 'regenerate-captcha';
        regenBtn.type = 'button';
        regenBtn.innerHTML = '🔄';
        regenBtn.style.border = 'none';
        regenBtn.style.background = 'transparent';
        regenBtn.style.cursor = 'pointer';
        regenBtn.style.marginLeft = '8px';
        regenBtn.style.fontSize = '1.2em';
        regenBtn.title = 'Regenerate CAPTCHA';

        questionElement.insertAdjacentElement('afterend', regenBtn);

        regenBtn.addEventListener('click', () => {
            generateCaptcha();
        });
    }
});


