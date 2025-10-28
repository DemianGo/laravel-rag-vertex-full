  async function checkDocumentProcessingStatus(documentId) {
    try {
      // Usar Python RAG para verificar chunks (sem LLM, só busca)
      const response = await doFetchJSON("POST", "/api/rag/python-search", {
        query: "test",
        document_id: documentId,
        top_k: 1,
        strictness: 3  // Sem LLM, só busca chunks
      });
      
      const chunksData = response.chunks || [];
      
      const chunks = Array.isArray(chunksData) ? chunksData : (chunksData.chunks || []);
      const currentChunks = chunks.length;
      
      console.log(`Document ${documentId}: ${currentChunks} chunks found`);
      
      // Se ainda não definimos o número esperado, definir agora
      if (expectedChunks === null) {
        if (currentChunks > 0) {
          // Chunks começaram a ser criados, definir número esperado
          expectedChunks = currentChunks;
          console.log(`Expecting ${expectedChunks} chunks for document ${documentId}`);
        }
        // Aguardar mais um ciclo para garantir que não há mais chunks sendo criados
        return false;
      }
      
      // Se o número de chunks mudou, atualizar expectativa
      if (currentChunks > expectedChunks) {
        expectedChunks = currentChunks;
        console.log(`Updated expectation to ${expectedChunks} chunks`);
        // Voltar a aguardar para garantir estabilidade
        return false;
      }
      
      // Se o número de chunks está estável e igual ao esperado, está pronto
      if (currentChunks === expectedChunks && currentChunks > 0) {
        console.log(`✅ Document ${documentId} completed: ${currentChunks} chunks ready!`);
        expectedChunks = null; // Reset para próximo documento
        return true;
      }
      
      return false;
    } catch (error) {
      console.error('Erro ao verificar status:', error);
      return false;
    }
  }
