-- ==========================================================================
-- SSMS - File : 21_uneb_module.sql
-- Module      : 2.4 Examination and Assessment Management (UNEB)
-- Depends on  : 00 - 04 (schools, students, subjects, academic_years, terms)
-- ==========================================================================

USE ssms;
SET NAMES utf8mb4;

-- --------------------------------------------------------------------------
-- UNEB Levels (UCE / UACE)
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS uneb_levels (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id   INT UNSIGNED NOT NULL,
  name        VARCHAR(60) NOT NULL,
  code        VARCHAR(20) NOT NULL,
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_uneb_level (school_id, code),
  CONSTRAINT fk_uneb_level_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
);

-- Make the two UNEB cycles available as soon as a school exists. The
-- application also creates them on first use, so this is only a convenience
-- for fresh installations where the seed data has already been imported.
INSERT IGNORE INTO uneb_levels (school_id, name, code)
SELECT id, 'UCE', 'UCE' FROM schools
UNION ALL
SELECT id, 'UACE', 'UACE' FROM schools;

-- --------------------------------------------------------------------------
-- UNEB Candidates (candidate registration for UCE/UACE)
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS uneb_candidates (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id       INT UNSIGNED NOT NULL,
  index_number    VARCHAR(30) NOT NULL,
  full_name       VARCHAR(160) NOT NULL,
  gender          ENUM('male','female') NOT NULL,
  date_of_birth   DATE,
  student_id      INT UNSIGNED,
  level_id        INT UNSIGNED NOT NULL,
  status          ENUM('registered','entered','withdrawn','completed') NOT NULL DEFAULT 'registered',
  notes           VARCHAR(255),
  registered_by   INT UNSIGNED,
  registered_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_uneb_index (school_id, index_number),
  KEY idx_uneb_level (level_id, status),
  CONSTRAINT fk_uneb_cand_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_uneb_cand_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE SET NULL,
  CONSTRAINT fk_uneb_cand_level FOREIGN KEY (level_id) REFERENCES uneb_levels (id) ON DELETE CASCADE
);

-- --------------------------------------------------------------------------
-- UNEB Candidate Subjects (subjects each candidate is entered for)
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS uneb_candidate_subjects (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  candidate_id  INT UNSIGNED NOT NULL,
  subject_id    INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_uneb_cand_subject (candidate_id, subject_id),
  CONSTRAINT fk_uneb_subj_candidate FOREIGN KEY (candidate_id) REFERENCES uneb_candidates (id) ON DELETE CASCADE,
  CONSTRAINT fk_uneb_subj_subject FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE CASCADE
);

-- --------------------------------------------------------------------------
-- UNEB Continuous Assessment Submission
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS uneb_ca_submission (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id        INT UNSIGNED NOT NULL,
  level_id         INT UNSIGNED NOT NULL,
  academic_year_id INT UNSIGNED NOT NULL,
  term_id          INT UNSIGNED,
  submission_type  ENUM('internal','uneb_portal') NOT NULL DEFAULT 'internal',
  status           ENUM('draft','submitted','confirmed') NOT NULL DEFAULT 'draft',
  notes            VARCHAR(255),
  submitted_by     INT UNSIGNED,
  submitted_at     DATETIME,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_uneb_ca_level (school_id, level_id, status),
  CONSTRAINT fk_uneb_ca_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_uneb_ca_level_lvl FOREIGN KEY (level_id) REFERENCES uneb_levels (id) ON DELETE CASCADE,
  CONSTRAINT fk_uneb_ca_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE CASCADE,
  CONSTRAINT fk_uneb_ca_term FOREIGN KEY (term_id) REFERENCES terms (id) ON DELETE SET NULL
);

-- --------------------------------------------------------------------------
-- UNEB CA Subject Scores
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS uneb_ca_subject_scores (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  submission_id  INT UNSIGNED NOT NULL,
  candidate_id   INT UNSIGNED NOT NULL,
  subject_id     INT UNSIGNED NOT NULL,
  ca_score       DECIMAL(5,2),
  project_score  DECIMAL(5,2),
  overall_score  DECIMAL(5,2),
  PRIMARY KEY (id),
  UNIQUE KEY uq_uneb_ca_score (submission_id, candidate_id, subject_id),
  KEY idx_uneb_ca_score_cand (candidate_id),
  CONSTRAINT fk_uneb_ca_score_sub FOREIGN KEY (submission_id) REFERENCES uneb_ca_submission (id) ON DELETE CASCADE,
  CONSTRAINT fk_uneb_ca_score_cand FOREIGN KEY (candidate_id) REFERENCES uneb_candidates (id) ON DELETE CASCADE,
  CONSTRAINT fk_uneb_ca_score_subj FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE CASCADE
);

-- --------------------------------------------------------------------------
-- UNEB National Results (stored after release by UNEB)
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS uneb_results (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id        INT UNSIGNED NOT NULL,
  level_id         INT UNSIGNED NOT NULL,
  academic_year_id INT UNSIGNED,
  exam_year        YEAR NOT NULL,
  index_number     VARCHAR(30) NOT NULL,
  full_name        VARCHAR(160) NOT NULL,
  gender           ENUM('male','female') NOT NULL,
  subject_results  TEXT,
  overall_level    VARCHAR(10),
  result_code      ENUM('Result 1','Result 2','Result 3'),
  ca_score_submitted TINYINT(1) NOT NULL DEFAULT 0,
  notes            VARCHAR(255),
  recorded_by      INT UNSIGNED,
  recorded_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_uneb_result (school_id, level_id, exam_year, index_number),
  KEY idx_uneb_result_year (exam_year, index_number),
  CONSTRAINT fk_uneb_res_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_uneb_res_level FOREIGN KEY (level_id) REFERENCES uneb_levels (id) ON DELETE CASCADE,
  CONSTRAINT fk_uneb_res_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE SET NULL
);
