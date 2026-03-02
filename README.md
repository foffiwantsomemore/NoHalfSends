# NHS

###### The acronym NHS stands for No Half Sends, that means to don't do things half.

The project involves the creation of a social network dedicated to sports, where registered users can share their activities and interact with other enthusiasts.

## User Profiles
After registering or logging in, each user has a personal profile associated with one or more sports (cycling, running, skiing, etc.).

Users can post sporting activities containing:
- 📊 Data related to the activity
- 📝 Descriptions
- 📷 Optional photographs  

The data collected varies depending on the type of sport practiced.

## Main Features
- 👀 Viewing activities posted by users  
- 🏃‍♂️ Filtering activities by type of sport  
- ❤️ Ability to **like** activities  
- 🗓️ Weekly and monthly summaries of activities within the user profile  
- 🥗 Advice page dedicated to sports, nutrition, and related topics  
- 📩 *(Features)* Messaging system linked to the advice page  

## Access Control
🛡️ Some features are only accessible to authenticated users.
🚫 Administrative pages are accessible exclusively to users with admin privileges.

## Framework Architecture
The project uses a lightweight custom framework based on:
- `.htaccess` for URL rewriting and centralized routing
- A `menuchoice` controller that dynamically loads pages
- A JSON-based page classification system
Each page is defined inside a JSON configuration file specifying its category and requirements, such as:
- Login required
- Admin access required
- Database connection needed
- Header and footer inclusion
The `menuchoice` component reads the JSON configuration and automatically includes or requires the necessary files depending on the page type.

This structure allows:
- Cleaner code organization  
- Reusable components (header, footer, authentication checks)  
- Centralized access control  
- Simplified scalability and maintainability  

## Scheme
Here you can find all the scheme of the project

![Scheme](media/ProjectScheme.svg)
