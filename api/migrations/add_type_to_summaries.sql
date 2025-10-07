-- Add type column to summaries table
ALTER TABLE summaries
ADD COLUMN type ENUM(
    'extractive',
    'abstractive',
    'executive',
    'key_points',
    'critical_analysis',
    'timeline',
    'mind_map',
    'faq',
    'swot',
    'pros_cons',
    'meeting_minutes',
    'book_summary',
    'research_abstract',
    'legal_brief',
    'technical_specs',
    'conclusion_focused',
    'methodology_focused',
    'application_focused',
    'problem_solution',
    'comparison_matrix',
    'step_guide',
    'case_study',
    'literature_review'
) DEFAULT 'abstractive' AFTER content;