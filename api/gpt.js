// api/gpt.js
const { getDbConnection } = require('./_utils/database');
const AuthUtils = require('./_utils/auth');
const ResponseUtils = require('./_utils/response');

module.exports = async (req, res) => {
  const action = req.query.action || 'generateSummary';
  const db = await getDbConnection();

  try {
    switch (action) {
      case 'generateSummary':
        return await handleGenerateSummary(req, res, db);
      case 'generateQuiz':
        return await handleGenerateQuiz(req, res, db);
      case 'extractKeywords':
        return await handleExtractKeywords(req, res, db);
      default:
        return ResponseUtils.badRequest(res, 'Invalid action');
    }
  } catch (error) {
    console.error('GPT/AI error:', error);
    return ResponseUtils.error(res, 'AI operation failed: ' + error.message);
  }
};

async function handleGenerateSummary(req, res, db) {
  try {
    // Get token from Authorization header
    const authHeader = req.headers.authorization;
    if (!authHeader || !authHeader.startsWith('Bearer ')) {
      return ResponseUtils.unauthorized(res);
    }

    const token = authHeader.substring(7);
    const decoded = AuthUtils.decodeJWT(token);

    if (!decoded) {
      return ResponseUtils.unauthorized(res);
    }

    const data = req.body;

    if (!data.text) {
      return ResponseUtils.badRequest(res, 'Missing text');
    }

    const length = data.length || 'medium';
    const format = data.format || 'paragraph';

    try {
      // TODO: Integrate with Google Gemini API
      const summary = await generateAISummary(data.text, length, format);
      return ResponseUtils.success(res, summary);
    } catch (error) {
      console.error('Summary generation failed:', error.message);
      // Return fallback summary
      const fallbackSummary = generateFallbackSummary(data.text, length, format);
      return ResponseUtils.success(res, fallbackSummary);
    }

  } catch (error) {
    console.error('Generate summary error:', error);
    return ResponseUtils.error(res, 'Failed to generate summary: ' + error.message);
  }
}

async function handleGenerateQuiz(req, res, db) {
  try {
    // Get token from Authorization header
    const authHeader = req.headers.authorization;
    if (!authHeader || !authHeader.startsWith('Bearer ')) {
      return ResponseUtils.unauthorized(res);
    }

    const token = authHeader.substring(7);
    const decoded = AuthUtils.decodeJWT(token);

    if (!decoded) {
      return ResponseUtils.unauthorized(res);
    }

    const data = req.body;

    if (!data.text) {
      return ResponseUtils.badRequest(res, 'Missing text');
    }

    const difficulty = data.difficulty || 'medium';
    const questionCount = data.questionCount || 5;
    const noteTitle = data.noteTitle || 'this study material';
    const quizType = data.quizType || 'multiple_choice';

    const options = {
      difficulty: difficulty,
      questionCount: questionCount,
      noteTitle: noteTitle,
      quizType: quizType
    };

    try {
      // TODO: Integrate with Google Gemini API for quiz generation
      const quiz = await generateAIQuiz(data.text, options);
      return ResponseUtils.success(res, quiz);
    } catch (error) {
      console.error('Quiz generation failed:', error.message);
      // Return fallback quiz
      const fallbackQuiz = generateFallbackQuiz(data.text, options);
      return ResponseUtils.success(res, fallbackQuiz);
    }

  } catch (error) {
    console.error('Generate quiz error:', error);
    return ResponseUtils.error(res, 'Failed to generate quiz: ' + error.message);
  }
}

async function handleExtractKeywords(req, res, db) {
  try {
    // Get token from Authorization header
    const authHeader = req.headers.authorization;
    if (!authHeader || !authHeader.startsWith('Bearer ')) {
      return ResponseUtils.unauthorized(res);
    }

    const token = authHeader.substring(7);
    const decoded = AuthUtils.decodeJWT(token);

    if (!decoded) {
      return ResponseUtils.unauthorized(res);
    }

    const data = req.body;

    if (!data.text) {
      return ResponseUtils.badRequest(res, 'Missing text');
    }

    const count = data.count || 5;

    try {
      // TODO: Integrate with AI service for keyword extraction
      const keywords = await extractAIKeywords(data.text, count);
      return ResponseUtils.success(res, keywords);
    } catch (error) {
      console.error('Keyword extraction failed:', error.message);
      // Return fallback keywords
      const fallbackKeywords = extractFallbackKeywords(data.text, count);
      return ResponseUtils.success(res, fallbackKeywords);
    }

  } catch (error) {
    console.error('Extract keywords error:', error);
    return ResponseUtils.error(res, 'Failed to extract keywords: ' + error.message);
  }
}

async function generateAISummary(text, length, format) {
  try {
    // TODO: Integrate with Google Gemini API
    // For now, return a placeholder that indicates AI integration is needed
    const wordCount = text.split(' ').length;

    switch (length) {
      case 'short':
        return `AI Summary (Short): ${text.substring(0, 100)}... (${wordCount} words total)`;
      case 'medium':
        return `AI Summary (Medium): ${text.substring(0, 200)}... (${wordCount} words total)`;
      case 'long':
        return `AI Summary (Long): ${text.substring(0, 300)}... (${wordCount} words total)`;
      default:
        return `AI Summary: ${text.substring(0, 150)}... (${wordCount} words total)`;
    }
  } catch (error) {
    console.error('AI summary generation error:', error);
    throw error;
  }
}

async function generateAIQuiz(text, options) {
  try {
    // TODO: Integrate with Google Gemini API for quiz generation
    // For now, return a placeholder that indicates AI integration is needed
    const questions = [];

    for (let i = 0; i < options.questionCount; i++) {
      questions.push({
        question: `AI Generated Question ${i + 1} about ${options.noteTitle}`,
        options: ['Option A', 'Option B', 'Option C', 'Option D'],
        correct_answer: 0,
        explanation: 'AI generated explanation would go here'
      });
    }

    return {
      title: `AI Generated Quiz: ${options.noteTitle}`,
      questions: questions,
      difficulty: options.difficulty,
      quiz_type: options.quizType,
      generated_by: 'AI Service'
    };
  } catch (error) {
    console.error('AI quiz generation error:', error);
    throw error;
  }
}

async function extractAIKeywords(text, count) {
  try {
    // TODO: Integrate with AI service for keyword extraction
    // For now, return basic keyword extraction
    const words = text.toLowerCase()
      .replace(/[^\w\s]/g, ' ')
      .split(/\s+/)
      .filter(word => word.length > 3)
      .filter(word => !['the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by'].includes(word));

    // Count word frequency
    const wordCount = {};
    words.forEach(word => {
      wordCount[word] = (wordCount[word] || 0) + 1;
    });

    // Sort by frequency and return top keywords
    return Object.entries(wordCount)
      .sort(([,a], [,b]) => b - a)
      .slice(0, count)
      .map(([word]) => word);
  } catch (error) {
    console.error('AI keyword extraction error:', error);
    throw error;
  }
}

function generateFallbackSummary(text, length, format = 'paragraph') {
  const wordCount = text.split(' ').length;
  const sentences = text.split(/[.!?]+/).filter(s => s.trim().length > 0);
  const firstSentence = sentences[0]?.trim() || '';

  switch (length) {
    case 'short':
      return "Summary: " + firstSentence.substring(0, 100) + "... (" + wordCount + " words)";
    case 'medium':
      const secondSentence = sentences[1] ? " " + sentences[1].trim() : "";
      return "Summary: " + firstSentence + secondSentence + " (Total: " + wordCount + " words)";
    case 'long':
      const summaryText = sentences.slice(0, 3).join(" ");
      return "Detailed Summary: " + summaryText + "... (Total: " + wordCount + " words, " + sentences.length + " sentences)";
    default:
      return "Generated summary for " + wordCount + " words of content.";
  }
}

function generateFallbackQuiz(text, options) {
  // Generate a basic quiz based on text analysis
  const sentences = text.split(/[.!?]+/).filter(s => s.trim().length > 20);
  const questions = [];

  for (let i = 0; i < Math.min(options.questionCount, sentences.length); i++) {
    const sentence = sentences[i].trim();
    questions.push({
      question: `What is the main point of: "${sentence.substring(0, 50)}..."`,
      options: [
        'Option A - First concept',
        'Option B - Second concept',
        'Option C - Third concept',
        'Option D - Fourth concept'
      ],
      correct_answer: Math.floor(Math.random() * 4),
      explanation: 'Based on the text analysis, this appears to be the correct interpretation.'
    });
  }

  return {
    title: `Quiz: ${options.noteTitle}`,
    questions: questions,
    difficulty: options.difficulty,
    quiz_type: options.quizType,
    generated_by: 'Fallback Service',
    note: 'AI quiz generation unavailable - using fallback generation'
  };
}

function extractFallbackKeywords(text, count) {
  const words = text.toLowerCase()
    .replace(/[^\w\s]/g, ' ')
    .split(/\s+/)
    .filter(word => word.length > 3)
    .filter(word => !['the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by'].includes(word));

  // Count word frequency
  const wordCount = {};
  words.forEach(word => {
    wordCount[word] = (wordCount[word] || 0) + 1;
  });

  // Sort by frequency and return top keywords
  return Object.entries(wordCount)
    .sort(([,a], [,b]) => b - a)
    .slice(0, count)
    .map(([word]) => word);
}