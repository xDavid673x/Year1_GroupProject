import os
import re

files_to_check = [
    "homepage/homepage.html",
    "Profile/profile.php",
    "Login_FAQs/leaderboard.php",
    "Login_FAQs/gemini.html",
    "Login_FAQs/login.html",
    "Login_FAQs/faq.html",
    "Gym_Locator/gym_locator.html"
]

pattern = re.compile(r'\s*\(\s*function\s+attachMobileNav\(\)\s*\{.*?\n\s*\}\)\(\);\s*', re.DOTALL)

for file in files_to_check:
    if os.path.exists(file):
        with open(file, 'r') as f:
            content = f.read()
        
        new_content = pattern.sub('\n', content)
        
        if new_content != content:
            with open(file, 'w') as f:
                f.write(new_content)
            print(f"Removed inline script from {file}")
