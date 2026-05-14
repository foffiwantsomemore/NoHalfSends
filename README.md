# NHS - No Half Sends

Languages: [English](README.md) | [Italiano](README.it.md)

No Half Sends is a sports-oriented social network where registered users can publish activities, follow other athletes, join sport-specific clubs, and read advice about training, nutrition, and recovery.

The name comes from the idea of never doing things halfway.

## Main Features

- User registration and login
- Personal athlete profile with image, description, followed users, followers, sports practiced, statistics, and recent activities
- Activity feed with posts from the user and followed athletes
- Sport-specific activity data for running, cycling, swimming, skiing, gym, and excursions
- Activity photos, likes, comments, and editing
- Follow/unfollow system between users
- Suggested athletes in the feed
- Clubs grouped by sport, with member/admin roles and club editing
- Advice page with likes, comments, photos, and admin-only creation/editing
- Responsive interface with shared navigation and page styling

## Custom Framework

The project uses a lightweight custom PHP framework based on `.htaccess`, `pages.json`, and `menuChoice.php`.

The `.htaccess` file uses `auto_prepend_file` to load `include/menu/menuChoice.php` before most PHP pages:

```apache
php_value auto_prepend_file "/XAMPP/htdocs/projects/NoHalfSends/include/menu/menuChoice.php"
```

`menuChoice.php` reads `include/pages.json` and decides what each page needs:

- `loggedInPages`: pages that require an authenticated user
- `DBPages`: pages that need the database handler
- `userpages`: pages that use the logged-in user navigation
- `homeOnly`: public pages that use the public navigation
- `adminpages`: admin-only pages

This keeps repeated session, login, database, and navigation setup out of individual pages as much as possible.

## Access Control

Pages listed in `loggedInPages` are protected through `include/header.php`, which starts the session, includes the database handler, and checks authentication through `include/loggedIn.php`.

Pages that only need the database, such as login and register actions, are listed in `DBPages`.

Admin-only UI is handled inside the relevant pages, for example the advice editing and publishing controls are shown only to users with the `admin` role.

## Database

The database schema is stored in `nohalfsends.sql`.

Main tables include:

- `User`
- `Sport`
- `Club`
- `UserClub`
- `SportUser`
- `Activity`
- Sport specialization tables such as `Run`, `Cycling`, `Swimming`, `Ski`, `Gym`, and `Excursion`
- `ActivityLike`, `ActivityComment`, `ActivityPhoto`
- `Follow`
- `Advice`, `AdviceLike`, `AdviceComment`, `AdvicePhoto`

The project also uses views to keep recurring queries cleaner:

- `v_user_profile`
- `v_user_sports`
- `v_user_activities`
- `v_club_detail`
- `v_user_followers`
- `v_user_following`

There are currently no required stored procedures in the active application code.

## Activity Specialization

Each activity stores common information in `Activity`, such as user, sport, date, duration, calories, elevation gain, and description.

Sport-specific metrics are stored in dedicated tables. For example:

- Running and cycling can store distance, pace/speed, heart rate, cadence, and elevation data
- Swimming can store pool/open-water data, distance, stroke type, laps, and pace
- Ski activities can store specialization-specific information through ski-related tables
- Gym activities can store training-specific data

This keeps the database normalized and avoids storing many unused columns in the base activity table.

## Clubs

Users can create and join clubs. Each club belongs to one sport and has:

- Name, description, image, and creation date
- Member count
- Activity count from club members
- Creator/admin information
- Recent activity preview

Club creators are stored as admins in the `UserClub` relationship table.

## Advice

The advice page contains articles grouped by category:

- Nutrition
- Training
- Recovery

Users can like and comment on advice. Admin users can create and edit advice posts, including optional images.

## Media Uploads

Uploaded media is stored under:

- `images/users/`
- `images/activities/`
- `images/clubs/`
- `images/advice/`

Default assets and icons are stored under `media/` and `images/sports/`.

## Scheme

Project scheme:

![Scheme](media/ProjectScheme.svg)
