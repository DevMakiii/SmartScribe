import api from './api';

/**
 * GPT Service for AI-powered text processing
 */
class GPTService {
  /**
   * Generate a summary of the provided text
   * @param {string} text - The text to summarize
   * @param {Object} options - Summary options
   * @param {string} options.length - Summary length ('short', 'medium', 'long')
   * @returns {Promise<Object>} - The summary result
   */
  async generateSummary(text, options = { length: 'medium' }) {
    try {
      const response = await api.gpt.generateSummary(text, options);
      return response.data;
    } catch (error) {
      console.error('Summary generation error:', error);
      throw new Error('Failed to generate summary. Please try again.');
    }
  }
  
  /**
   * Generate a quiz based on the provided text
   * @param {string} text - The text to create a quiz from
   * @param {Object} options - Quiz options
   * @param {string} options.difficulty - Quiz difficulty ('easy', 'medium', 'hard')
   * @param {number} options.questionCount - Number of questions to generate
   * @returns {Promise<Object>} - The quiz result
   */
  async generateQuiz(text, options = { difficulty: 'medium', questionCount: 5 }) {
    try {
      const response = await api.gpt.generateQuiz(text, options);
      return response.data;
    } catch (error) {
      console.error('Quiz generation error:', error);
      throw new Error('Failed to generate quiz. Please try again.');
    }
  }
  
  /**
   * Extract keywords from the provided text
   * @param {string} text - The text to extract keywords from
   * @returns {Promise<Object>} - The keywords result
   */
  async extractKeywords(text) {
    try {
      const response = await api.gpt.extractKeywords(text);
      return response.data;
    } catch (error) {
      console.error('Keyword extraction error:', error);
      throw new Error('Failed to extract keywords. Please try again.');
    }
  }
  
  /**
   * Check if the text is appropriate (no harmful content)
   * @param {string} text - The text to check
   * @returns {Promise<boolean>} - Whether the text is appropriate
   */
  async isAppropriateContent(text) {
    // This would typically call a content moderation API
    // For now, we'll implement a simple check
    const inappropriateTerms = [
      'inappropriate', 'offensive', 'harmful', 'illegal', 'explicit'
    ];
    
    const lowerText = text.toLowerCase();
    return !inappropriateTerms.some(term => lowerText.includes(term));
  }
}

export default new GPTService();