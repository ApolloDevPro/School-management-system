-- ==========================================================================
-- SSMS - File : 14_cocurricular_module.sql
-- Module      : 2.16 Co-curricular Activities Management
-- Depends on  : 00 - 03 (schools, students, staff, houses)
-- ==========================================================================

USE ssms;
SET NAMES utf8mb4;

-- --------------------------------------------------------------------------
-- Clubs and Societies
-- --------------------------------------------------------------------------
CREATE TABLE clubs (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id        INT UNSIGNED NOT NULL,
  name             VARCHAR(140) NOT NULL,
  code             VARCHAR(20),
  club_category    ENUM('academic','religious','cultural','service','sports','interest','governance') NOT NULL DEFAULT 'interest',
  description      VARCHAR(500),
  patron_staff_id  INT UNSIGNED,
  co_patron_staff_id INT UNSIGNED,
  meeting_day      ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'),
  meeting_time     TIME,
  meeting_venue    VARCHAR(120),
  membership_limit SMALLINT,
  registration_fee DECIMAL(12,2),
  academic_year_id INT UNSIGNED,
  status           ENUM('active','inactive','dissolved') NOT NULL DEFAULT 'active',
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_club (school_id,name),
  KEY idx_club_patron (patron_staff_id),
  CONSTRAINT fk_club_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_club_patron FOREIGN KEY (patron_staff_id) REFERENCES staff (id) ON DELETE SET NULL,
  CONSTRAINT fk_club_copatron FOREIGN KEY (co_patron_staff_id) REFERENCES staff (id) ON DELETE SET NULL,
  CONSTRAINT fk_club_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE SET NULL
);

CREATE TABLE club_memberships (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  club_id        INT UNSIGNED NOT NULL,
  student_id     INT UNSIGNED NOT NULL,
  academic_year_id INT UNSIGNED NOT NULL,
  member_role    ENUM('member','executive','treasurer','secretary','president','captain') NOT NULL DEFAULT 'member',
  joined_date    DATE NOT NULL,
  left_date      DATE,
  status         ENUM('active','left','suspended','expelled') NOT NULL DEFAULT 'active',
  attendance_rate DECIMAL(5,2),
  remarks        VARCHAR(255),
  PRIMARY KEY (id),
  UNIQUE KEY uq_club_member (club_id,student_id,academic_year_id),
  KEY idx_club_student (student_id,status),
  CONSTRAINT fk_cm_club FOREIGN KEY (club_id) REFERENCES clubs (id) ON DELETE CASCADE,
  CONSTRAINT fk_cm_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
  CONSTRAINT fk_cm_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE CASCADE
);

-- --------------------------------------------------------------------------
-- Sports and Games
-- --------------------------------------------------------------------------
CREATE TABLE sports_teams (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id        INT UNSIGNED NOT NULL,
  team_name        VARCHAR(140) NOT NULL,
  sport_category   ENUM('football','netball','basketball','athletics','volleyball','cricket','handball','swimming','other') NOT NULL,
  team_type        ENUM('boys','girls','mixed') NOT NULL DEFAULT 'boys',
  age_group        ENUM('junior','senior','mixed'),
  coach_staff_id   INT UNSIGNED,
  assistant_coach_staff_id INT UNSIGNED,
  season           VARCHAR(30),
  academic_year_id INT UNSIGNED,
  training_day     ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'),
  training_time    TIME,
  training_venue   VARCHAR(120),
  status           ENUM('active','inactive','archived') NOT NULL DEFAULT 'active',
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_team (school_id,team_name,season),
  CONSTRAINT fk_team_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_team_coach FOREIGN KEY (coach_staff_id) REFERENCES staff (id) ON DELETE SET NULL,
  CONSTRAINT fk_team_assistant FOREIGN KEY (assistant_coach_staff_id) REFERENCES staff (id) ON DELETE SET NULL,
  CONSTRAINT fk_team_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE SET NULL
);

CREATE TABLE team_members (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  team_id        INT UNSIGNED NOT NULL,
  student_id     INT UNSIGNED NOT NULL,
  member_role    ENUM('player','captain','vice_captain','substitute','manager') NOT NULL DEFAULT 'player',
  shirt_number   SMALLINT,
  joined_date    DATE NOT NULL,
  status         ENUM('active','injured','dropped','left') NOT NULL DEFAULT 'active',
  remarks        VARCHAR(255),
  PRIMARY KEY (id),
  UNIQUE KEY uq_team_member (team_id,student_id),
  KEY idx_team_student (student_id),
  CONSTRAINT fk_tm_team FOREIGN KEY (team_id) REFERENCES sports_teams (id) ON DELETE CASCADE,
  CONSTRAINT fk_tm_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE
);

CREATE TABLE fixtures (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  team_id          INT UNSIGNED NOT NULL,
  opponent         VARCHAR(160) NOT NULL,
  opponent_type    ENUM('inter_school','house','national','friendly','tournament') NOT NULL DEFAULT 'inter_school',
  venue            VARCHAR(160),
  home_away        ENUM('home','away','neutral') NOT NULL DEFAULT 'home',
  fixture_date     DATE NOT NULL,
  start_time       TIME,
  competition_id   INT UNSIGNED,
  category         ENUM('league','friendly','cup','knockout','house','training') NOT NULL DEFAULT 'friendly',
  transport_required TINYINT(1) NOT NULL DEFAULT 0,
  status           ENUM('scheduled','live','played','postponed','cancelled','abandoned') NOT NULL DEFAULT 'scheduled',
  notes            VARCHAR(255),
  created_by       INT UNSIGNED,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_fixture_date (fixture_date,status),
  KEY idx_fixture_team (team_id,fixture_date),
  CONSTRAINT fk_fixture_team FOREIGN KEY (team_id) REFERENCES sports_teams (id) ON DELETE CASCADE
);

CREATE TABLE fixture_results (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  fixture_id        INT UNSIGNED NOT NULL,
  our_score         SMALLINT NOT NULL DEFAULT 0,
  opponent_score    SMALLINT NOT NULL DEFAULT 0,
  result_outcome    ENUM('win','draw','loss'),
  half_time_score   VARCHAR(20),
  mvp_student_id    INT UNSIGNED,
  scorers           TEXT,
  team_of_the_day   TEXT,
  referee           VARCHAR(140),
  attendance_count  SMALLINT,
  remarks           VARCHAR(500),
  recorded_by       INT UNSIGNED,
  recorded_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_fixture_result (fixture_id),
  CONSTRAINT fk_fr_fixture FOREIGN KEY (fixture_id) REFERENCES fixtures (id) ON DELETE CASCADE,
  CONSTRAINT fk_fr_student FOREIGN KEY (mvp_student_id) REFERENCES students (id) ON DELETE SET NULL
);

-- --------------------------------------------------------------------------
-- House Competitions
-- --------------------------------------------------------------------------
CREATE TABLE house_competition_points (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  house_id         INT UNSIGNED NOT NULL,
  academic_year_id INT UNSIGNED NOT NULL,
  term_id          INT UNSIGNED,
  category         ENUM('sports','academics','discipline','cultural','cleanliness','cooking','other') NOT NULL,
  event_name       VARCHAR(160) NOT NULL,
  points           DECIMAL(6,2) NOT NULL DEFAULT 0,
  points_type      ENUM('award','penalty') NOT NULL DEFAULT 'award',
  event_date       DATE NOT NULL,
  description      VARCHAR(255),
  awarded_by       INT UNSIGNED,
  source_fixture_id INT UNSIGNED,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_house_points (house_id,academic_year_id,term_id),
  KEY idx_house_event_date (event_date),
  CONSTRAINT fk_hcp_house FOREIGN KEY (house_id) REFERENCES houses (id) ON DELETE CASCADE,
  CONSTRAINT fk_hcp_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE CASCADE,
  CONSTRAINT fk_hcp_term FOREIGN KEY (term_id) REFERENCES terms (id) ON DELETE CASCADE,
  CONSTRAINT fk_hcp_fixture FOREIGN KEY (source_fixture_id) REFERENCES fixtures (id) ON DELETE SET NULL
);

-- --------------------------------------------------------------------------
-- Competitions and Events (inter-school / national)
-- --------------------------------------------------------------------------
CREATE TABLE competitions (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id        INT UNSIGNED NOT NULL,
  name             VARCHAR(180) NOT NULL,
  competition_type ENUM('music','drama','science_fair','sports','debate','quiz','agriculture','ict','other') NOT NULL,
  level            ENUM('school','district','regional','national','international') NOT NULL DEFAULT 'school',
  organiser        VARCHAR(160),
  venue            VARCHAR(160),
  start_date       DATE,
  end_date         DATE,
  budget           DECIMAL(14,2),
  transport_required TINYINT(1) NOT NULL DEFAULT 0,
  status           ENUM('planned','registration_open','participating','completed','cancelled') NOT NULL DEFAULT 'planned',
  results_summary  TEXT,
  created_by       INT UNSIGNED,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_competition_type (competition_type,level,start_date),
  CONSTRAINT fk_competition_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
);

ALTER TABLE fixtures
  ADD CONSTRAINT fk_fixture_competition FOREIGN KEY (competition_id) REFERENCES competitions (id) ON DELETE SET NULL;

CREATE TABLE competition_entries (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  competition_id   INT UNSIGNED NOT NULL,
  student_id       INT UNSIGNED,
  entry_name       VARCHAR(180),
  category         VARCHAR(120),
  participation_role ENUM('solo','team_member','team_leader','support','exhibitor') NOT NULL DEFAULT 'solo',
  club_id          INT UNSIGNED,
  team_id          INT UNSIGNED,
  registration_date DATE,
  fee_amount       DECIMAL(12,2),
  position         VARCHAR(40),
  score            DECIMAL(6,2),
  award            VARCHAR(120),
  certificate_path VARCHAR(255),
  remarks          VARCHAR(500),
  status           ENUM('registered','confirmed','participated','withdrew','disqualified') NOT NULL DEFAULT 'registered',
  PRIMARY KEY (id),
  UNIQUE KEY uq_entry (competition_id,student_id),
  KEY idx_entry_student (student_id),
  CONSTRAINT fk_entry_competition FOREIGN KEY (competition_id) REFERENCES competitions (id) ON DELETE CASCADE,
  CONSTRAINT fk_entry_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
  CONSTRAINT fk_entry_club FOREIGN KEY (club_id) REFERENCES clubs (id) ON DELETE SET NULL,
  CONSTRAINT fk_entry_team FOREIGN KEY (team_id) REFERENCES sports_teams (id) ON DELETE SET NULL
);

-- --------------------------------------------------------------------------
-- Talent and Achievement Records
-- --------------------------------------------------------------------------
CREATE TABLE talent_records (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id       INT UNSIGNED NOT NULL,
  talent_type      ENUM('music','sport','drama','art','leadership','academics','crafts','other') NOT NULL,
  talent_name      VARCHAR(160) NOT NULL,
  description      VARCHAR(500),
  level            ENUM('class','school','district','regional','national','international') NOT NULL DEFAULT 'school',
  achievement      VARCHAR(255),
  award_title      VARCHAR(160),
  award_date       DATE,
  certificate_path VARCHAR(255),
  leadership_role  VARCHAR(120),
  date_recorded    DATE NOT NULL,
  verified_by      INT UNSIGNED,
  is_testimonial_note TINYINT(1) NOT NULL DEFAULT 0,
  notes            VARCHAR(500),
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_talent_student (student_id,talent_type),
  KEY idx_talent_level (level,award_date),
  CONSTRAINT fk_talent_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE
);

