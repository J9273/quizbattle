-- Migration: Add Multiple Choice Quiz Format Support
-- Run this in your PostgreSQL database

-- 1. Add quiz_format column to quiz_episodes table
ALTER TABLE quiz_episodes 
ADD COLUMN IF NOT EXISTS quiz_format VARCHAR(20) DEFAULT 'cutthroat' 
CHECK (quiz_format IN ('cutthroat', 'multiple_choice'));

COMMENT ON COLUMN quiz_episodes.quiz_format IS 'Quiz format: cutthroat (buzz-in) or multiple_choice';

-- 2. Add format and multiple choice fields to questions table
ALTER TABLE questions 
ADD COLUMN IF NOT EXISTS question_format VARCHAR(20) DEFAULT 'cutthroat' 
CHECK (question_format IN ('cutthroat', 'multiple_choice', 'both'));

ALTER TABLE questions 
ADD COLUMN IF NOT EXISTS choice_a TEXT,
ADD COLUMN IF NOT EXISTS choice_b TEXT,
ADD COLUMN IF NOT EXISTS choice_c TEXT,
ADD COLUMN IF NOT EXISTS choice_d TEXT,
ADD COLUMN IF NOT EXISTS correct_choice VARCHAR(1) CHECK (correct_choice IN ('A', 'B', 'C', 'D'));

COMMENT ON COLUMN questions.question_format IS 'Question format: cutthroat, multiple_choice, or both';
COMMENT ON COLUMN questions.choice_a IS 'Multiple choice option A';
COMMENT ON COLUMN questions.choice_b IS 'Multiple choice option B';
COMMENT ON COLUMN questions.choice_c IS 'Multiple choice option C';
COMMENT ON COLUMN questions.choice_d IS 'Multiple choice option D (optional)';
COMMENT ON COLUMN questions.correct_choice IS 'Correct answer: A, B, C, or D';

-- 3. Create multiple_choice_answers table for tracking player selections
CREATE TABLE IF NOT EXISTS multiple_choice_answers (
    id SERIAL PRIMARY KEY,
    episode_id INTEGER NOT NULL REFERENCES quiz_episodes(id) ON DELETE CASCADE,
    team_id INTEGER NOT NULL REFERENCES teams(id) ON DELETE CASCADE,
    question_id INTEGER NOT NULL REFERENCES questions(id) ON DELETE CASCADE,
    selected_choice VARCHAR(1) NOT NULL CHECK (selected_choice IN ('A', 'B', 'C', 'D')),
    is_correct BOOLEAN NOT NULL,
    answered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    time_taken_seconds INTEGER,
    UNIQUE(episode_id, team_id, question_id)
);

CREATE INDEX IF NOT EXISTS idx_mc_answers_episode ON multiple_choice_answers(episode_id);
CREATE INDEX IF NOT EXISTS idx_mc_answers_team ON multiple_choice_answers(team_id);
CREATE INDEX IF NOT EXISTS idx_mc_answers_question ON multiple_choice_answers(question_id);

COMMENT ON TABLE multiple_choice_answers IS 'Stores player answers for multiple choice questions';
COMMENT ON COLUMN multiple_choice_answers.selected_choice IS 'The choice the player selected (A, B, C, or D)';
COMMENT ON COLUMN multiple_choice_answers.is_correct IS 'Whether the answer was correct';
COMMENT ON COLUMN multiple_choice_answers.time_taken_seconds IS 'Time taken to answer (optional, for timed questions)';

-- 4. Update episode_state to track current format
ALTER TABLE episode_state 
ADD COLUMN IF NOT EXISTS question_format VARCHAR(20);

-- 5. Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_questions_format ON questions(question_format);
CREATE INDEX IF NOT EXISTS idx_episodes_format ON quiz_episodes(quiz_format);

-- 6. Sample data update (optional - makes existing questions work with both formats)
-- Uncomment if you want existing questions to work in both modes
-- UPDATE questions SET question_format = 'both' WHERE question_format IS NULL;

-- Done! Your database now supports both quiz formats.
