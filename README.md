# NHS

###### The acronym NHS stands for *No Half Sends*, meaning to never do things halfway.

The project consists of the development of a sports-oriented social network where registered users can share their activities and interact with other sports enthusiasts.

## User Profiles
After registering or logging in, each user has a personal profile associated with one or more sports (cycling, running, skiing, etc.).

Users can:
- Publish sports activities
- Follow other users
- Join sport-specific clubs
- Interact through likes and comments

Each activty may include:
- 📊 Data related to the activity
- 📝 Descriptions
- 📷 Optional photographs  
The data collected varies depending on the type of sport practiced.

## Main Features
- 👀 View activities posted by followed users
- 🏃‍♂️ Filter activities by sport
- ❤️ Like activities
- 💬 Comment on activities
- 🗓️ Weekly and monthly summaries inside the user profile
- 🥗 Advice page dedicated to sport and nutrition
- 👥 Follow / subscription system between users
- 🏆 Sport-specific clubs with member roles

## Clubs System
Users can join multiple clubs, and each club belongs to a single sport.
Features include:
- Many-to-many relationship between users and clubs
- Join date tracking
- Role management inside the club (member, admin)
- Dynamic member count (calculated via query)

## Activity Specialization
Activities inherit common attributes (date, duration, calories, etc.)  
Each sport has specific technical data, implemented through specialization tables.
This structure ensures normalization and avoids redundant data.

## Access Control
🛡️ Some features are only accessible to authenticated users:
- Posting activities
- Commenting
- Liking
- Joining clubs
- Following users

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
