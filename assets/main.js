// Simple FAQ toggle functionality
document.addEventListener('DOMContentLoaded', function() {
    const faqQuestions = document.querySelectorAll('.faq-question');
    
    faqQuestions.forEach(question => {
        question.addEventListener('click', function() {
            const answer = this.nextElementSibling;
            const icon = this.querySelector('i');
            
            if (answer.style.display === 'block') {
                answer.style.display = 'none';
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
            } else {
                answer.style.display = 'block';
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
            }
        });
    });
});



// Improved JavaScript for Mega Menu
document.addEventListener('DOMContentLoaded', function() {
    const isDesktop = window.innerWidth >= 992;
    
    // Function to handle dropdown toggling
    function handleDropdowns() {
      const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
      
      dropdownToggles.forEach(toggle => {
        // Clear any existing event listeners
        toggle.removeEventListener('click', toggleDropdown);
        
        // Add click event for mobile
        if (!isDesktop) {
          toggle.addEventListener('click', toggleDropdown);
        }
      });
      
      // Handle hover for desktop
      if (isDesktop) {
        const dropdowns = document.querySelectorAll('.dropdown');
        
        dropdowns.forEach(dropdown => {
          dropdown.addEventListener('mouseenter', function() {
            const menu = this.querySelector('.dropdown-menu');
            menu.classList.add('show');
          });
          
          dropdown.addEventListener('mouseleave', function() {
            const menu = this.querySelector('.dropdown-menu');
            menu.classList.remove('show');
          });
        });
      }
    }
    
    // Toggle dropdown function for mobile
    function toggleDropdown(e) {
      e.preventDefault();
      e.stopPropagation();
      
      const parent = this.parentElement;
      const menu = parent.querySelector('.dropdown-menu');
      
      // Close all other dropdowns
      document.querySelectorAll('.dropdown-menu.show').forEach(openMenu => {
        if (openMenu !== menu) {
          openMenu.classList.remove('show');
        }
      });
      
      // Toggle this dropdown
      menu.classList.toggle('show');
    }
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
      if (!e.target.closest('.dropdown')) {
        document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
          menu.classList.remove('show');
        });
      }
    });
    
    // Initial setup
    handleDropdowns();
    
    // Update on window resize
    let resizeTimer;
    window.addEventListener('resize', function() {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(function() {
        const wasDesktop = isDesktop;
        const isNowDesktop = window.innerWidth >= 992;
        
        // Only update if the breakpoint changed
        if (wasDesktop !== isNowDesktop) {
          handleDropdowns();
        }
      }, 250);
    });
    
    // Fix for Bootstrap's dropdown behavior conflicting with our custom code
    const dropdownMenus = document.querySelectorAll('.dropdown-menu');
    dropdownMenus.forEach(menu => {
      menu.addEventListener('click', function(e) {
        // Prevent closing when clicking inside mega menu
        if (menu.classList.contains('mega-menu')) {
          e.stopPropagation();
        }
      });
    });
  });


  
  function showTab(evt, tabId) {
            // Hide all content
            var contents = document.getElementsByClassName('cyberwhy-content');
            for (var i = 0; i < contents.length; i++) {
                contents[i].classList.remove('active');
            }
            
            // Remove active class from all tabs
            var tabs = document.getElementsByClassName('cyberwhy-tab');
            for (var i = 0; i < tabs.length; i++) {
                tabs[i].classList.remove('active');
            }
            
            // Show selected content and mark tab as active
            document.getElementById(tabId).classList.add('active');
            evt.currentTarget.classList.add('active');
        }


     function downloadDocument(documentType) {
            // Simulate download functionality
            const documentNames = {
                'business-insurance': 'Documents_Business_Insurance_Proposal_Form.pdf',
                'professional-indemnity': 'Professional_Indemnity_Proposal_Form.pdf',
                'truck-insurance': 'Truck_Insurance_Proposal_Form.pdf'
            };
            
            const fileName = documentNames[documentType];
            
            // Create a temporary element to trigger download
            const link = document.createElement('a');
            link.href = '#'; // In real implementation, this would be the actual file URL
            link.download = fileName;
            
            // Show download message
            const button = event.target.closest('.download-button');
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-check"></i> Downloaded!';
            button.style.background = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';
            
            setTimeout(() => {
                button.innerHTML = originalText;
                button.style.background = 'linear-gradient(135deg, #f97316 0%, #ea580c 100%)';
            }, 2000);
            
            console.log(`Downloading: ${fileName}`);
        }